<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
header("Content-Type: application/json");
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'telegram')) {
	echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (telegram)', __FILE__);
	die();
}

$content = file_get_contents('php://input');
$json = json_decode($content, true);
log::add('telegram', 'debug', $content);

$id = init('id');
$eqLogic = telegram::byId($id);
if (!is_object($eqLogic)) {
	echo json_encode(array('text' => __('Id inconnu : ', __FILE__) . init('id')));
	die();
}

$parameters = array();

if ($json["message"]["chat"]["type"] == 'private') {
	$username = isset($json["message"]["from"]["username"]) ? $json["message"]["from"]["username"] : $json["message"]["from"]["first_name"];
} else if ($json["message"]["chat"]["type"] == 'group') {
	$username = $json["message"]["chat"]["title"];
} else {
	log::add('telegram', 'debug', 'Message non supporté');
	die();
}
log::add('telegram', 'debug', 'Recu message de ' . $username);

foreach ($eqLogic->getCmd('action') as $cmd) {
	if ($cmd->askResponse($json["message"]["text"])) {
		echo json_encode(array('text' => ''));
		die();
	}
}

$cmd_user = $eqLogic->getCmd('action', $json["message"]["chat"]["id"]);
if (is_object($cmd_user)) {
	$parameters['reply_cmd'] = $cmd_user;
	$user = user::byId($cmd_user->getConfiguration('user'));
	if (is_object($user)) {
		$parameters['profile'] = $user->getLogin();
	}
}

$eqLogic->checkAndUpdateCmd('sender', trim($json["message"]["from"]["id"]));
$eqLogic->checkAndUpdateCmd('chat', trim($json["message"]["chat"]["id"]));
$interactAnswer = 0;
if (isset($json["message"]["text"])) {
	$eqLogic->checkAndUpdateCmd('text', $json["message"]["text"]);
	if (!is_object($cmd_user)) {
		if ($eqLogic->getConfiguration('isAccepting') != 1) {
			die();
		}
		$cmd_user = new telegramCmd();
		$cmd_user->setLogicalId($json["message"]["chat"]["id"]);
		$cmd_user->setIsVisible(1);
		$cmd_user->setName($username . ' - ' . $json["message"]["chat"]["id"]);
		$cmd_user->setConfiguration('interact', 1);
		$cmd_user->setConfiguration('chatid', $json["message"]["chat"]["id"]);
		$cmd_user->setType('action');
		$cmd_user->setSubType('message');
		$cmd_user->setEqLogic_id($eqLogic->getId());
		$cmd_user->setDisplay('title_placeholder', __('Options', __FILE__));
	}
	if (isset($json["message"]["chat"]["title"])) {
		$cmd_user->setConfiguration('title', $json["message"]["chat"]["title"]);
	} else {
		if (isset($json["message"]["from"]["username"])) {
			$cmd_user->setConfiguration('username', $json["message"]["from"]["username"]);
		}
		if (isset($json["message"]["from"]["first_name"])) {
			$cmd_user->setConfiguration('last_name', $json["message"]["from"]["first_name"]);
		}
		if (isset($json["message"]["from"]["last_name"])) {
			$cmd_user->setConfiguration('last_name', $json["message"]["from"]["last_name"]);
		}
	}
	$cmd_user->save();
	if (isset($json["message"]["reply_to_message"])) {
		die();
	}

  if ($cmd_user->getConfiguration('interact') == 1) {
		$interactAnswer = 1;
		$parameters['plugin'] = 'telegram';
		$reply = interactQuery::tryToReply(trim($json["message"]["text"]), $parameters);
	} else {
		$reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu');
	}
	$file_id = '';
} else if (isset($json["message"]["document"])) {
	$file_id = $json["message"]["document"]["file_id"];
	$file_name = $json["message"]["document"]["file_name"];
	$reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Document)';
} else if (isset($json["message"]["photo"])) {
	$file_id = $json["message"]["photo"]["file_id"];
	$file_name = $username . '.png';
	$reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Photo)';
} else if (isset($json["message"]["video"])) {
	$file_id = $json["message"]["video"]["file_id"];
	$file_name = $username . '.mp4';
	$reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Vidéo)';
} else if (isset($json["message"]["location"])) {
	$file_id = '';
	$cmd_user = $eqLogic->getCmd('action', $json["message"]["chat"]["id"]);
	if (is_object($cmd_user)) {
		$geoloc = str_replace('#', '', $cmd_user->getConfiguration('cmdgeoloc', ''));
		$geolocCmd = geolocCmd::byId($geoloc);
		if (is_object($geolocCmd)) {
			$geolocCmd->event($json["message"]["location"]["latitude"] . ',' . $json["message"]["location"]["longitude"]);
			$geolocCmd->save();
		}
	}
	$reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Localisation)';
}

if (!$eqLogic->getConfiguration('noreply', 0) || $interactAnswer == 1) {
	$answer = array(
		'method' => 'sendMessage',
		'chat_id' => $json['message']['chat']['id'],
		'text' => $reply['reply'],
	);
	echo json_encode($answer);
}

if (isset($reply['file']) && count($reply['file']) > 0) {
	if (!is_array($reply['file'])) {
		$reply['file'] = array($reply['file']);
	}
	$cmd_user->execCmd(array('files' => $reply['file']));
}

if ($file_id != '' && $eqLogic->getConfiguration('savepath', '') != '') {
	$url = "https://api.telegram.org/bot" . trim($eqLogic->getConfiguration('bot_token')) . '/getFile';
	$post_fields['file_id'] = $file_id;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
	$output = json_decode(curl_exec($ch), true);
	$local_file_path = $eqLogic->getConfiguration('savepath') . '/' . $file_name;
	$file_url = "https://api.telegram.org/file/bot" . trim($eqLogic->getConfiguration('bot_token')) . "/" . $output["result"]["file_path"];
	$in = fopen($file_url, "rb");
	$out = fopen($local_file_path, "wb");
	while ($chunk = fread($in, 8192)) {
		fwrite($out, $chunk, 8192);
	}
	fclose($in);
	fclose($out);
	$eqLogic->checkAndUpdateCmd('text', 'file:' . $local_file_path);
}
die();

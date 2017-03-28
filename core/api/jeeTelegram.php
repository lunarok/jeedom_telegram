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
header('Content-type: application/json');
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
if (isset($json["message"]["from"]["username"])) {
	log::add('telegram', 'debug', 'Recu message de ' . $json["message"]["from"]["username"] . ' texte : ' . $json["message"]["text"]);
	$username = $json["message"]["from"]["username"];
	$username = strtr(utf8_decode($username), utf8_decode('ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ'), 'AAAAAAAECEEEEIIIIDNOOOOOOUUUUYsaaaaaaaeceeeeiiiinoooooouuuuyyAaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIiIJijJjKkLlLlLlLlllNnNnNnnOoOoOoOEoeRrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzsfOoUuAaIiOoUuUuUuUuUuAaAEaeOo');
	$username = strtolower($username);
	$user = user::byLogin($username);
	if (is_object($user)) {
		$parameters['profile'] = $json["message"]["from"]["username"];
	}
} else {
	$username = $json["message"]["from"]["first_name"];
	$username = strtr(utf8_decode($username), utf8_decode('ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ'), 'AAAAAAAECEEEEIIIIDNOOOOOOUUUUYsaaaaaaaeceeeeiiiinoooooouuuuyyAaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIiIJijJjKkLlLlLlLlllNnNnNnnOoOoOoOEoeRrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzsfOoUuAaIiOoUuUuUuUuUuAaAEaeOo');
	$username = strtolower($username);
	$user = user::byLogin($username);
	if (is_object($user)) {
		$parameters['profile'] = $json["message"]["from"]["first_name"];
	}
}

foreach ($eqLogic->getCmd('action') as $cmd) {
	if ($json["message"]["chat"]["id"] == $cmd->getConfiguration('chatid') && $cmd->getCache('storeVariable', 'none') != 'none') {
		$dataStore = new dataStore();
		$dataStore->setType('scenario');
		$dataStore->setKey($cmd->getCache('storeVariable', 'none'));
		$dataStore->setValue($json["message"]["text"]);
		$dataStore->setLink_id(-1);
		$dataStore->save();
		$cmd->setCache('storeVariable', 'none');
		$cmd->save();
		echo json_encode(array('text' => ''));
		die();
	}
}

$cmd_text = $eqLogic->getCmd('info', 'text');
$cmd_text->event(trim($json["message"]["text"]));
$cmd_text->setValue($json["message"]["text"]);
$cmd_text->setConfiguration('value',$json["message"]["text"]);
$cmd_text->save();
$cmd_sender = $eqLogic->getCmd('info', 'sender');
$cmd_sender->event($json["message"]["chat"]["id"]);
$cmd_sender->setValue($json["message"]["chat"]["id"]);
$cmd_sender->setConfiguration('value',$json["message"]["chat"]["id"]);
$cmd_sender->save();

$cmd_user = $eqLogic->getCmd('action', $json["message"]["chat"]["id"]);
if (!is_object($cmd_user)) {
	if ($eqLogic->getConfiguration('isAccepting') == 1) {
		$cmd_user = new telegramCmd();
		$cmd_user->setLogicalId($json["message"]["chat"]["id"]);
		$cmd_user->setIsVisible(1);
		$cmd_user->setName($json["message"]["from"]["username"]);
		$cmd_user->setConfiguration('interact',0);
		$cmd_user->setConfiguration('chatid',$json["message"]["chat"]["id"]);
		$cmd_user->setConfiguration('firstname',$json["message"]["from"]["first_name"]);
		$cmd_user->setType('action');
		$cmd_user->setSubType('message');
		$cmd_user->setEqLogic_id($eqLogic->getId());
		$cmd_user->save();
	} else {
		die();
	}
}
if (isset($json["message"]["from"]["username"])) {
	$cmd_user->setConfiguration('username',$json["message"]["from"]["username"]);
	$cmd_user->save();
}


if ($cmd_user->getConfiguration('interact') == 1) {
	$reply = interactQuery::tryToReply(trim($json["message"]["text"]), $parameters);
} else {
	$reply['reply'] = 'Message recu';
}

$answer = array('method' => 'sendMessage', 'chat_id' => $json["message"]["chat"]["id"], "reply_to_message_id" => $json["message"]["message_id"], "text" => $reply['reply']);

header("Content-Type: application/json");
echo json_encode($answer);
return true;

?>

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

if ($json["message"]["chat"]["type"] == 'private') {
    if (isset($json["message"]["from"]["username"])) {
    	$username = $json["message"]["from"]["username"];
    } else {
    	$username = $json["message"]["from"]["first_name"];
    }
} else if ($json["message"]["chat"]["type"] == 'group') {
    $username = $json["message"]["chat"]["title"];
} else {
    log::add('telegram', 'debug', 'Message non supporté');
    return;
}
log::add('telegram', 'debug', 'Recu message de ' . $username);
$username = strtolower(strtr(utf8_decode($username), utf8_decode('ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ'), 'AAAAAAAECEEEEIIIIDNOOOOOOUUUUYsaaaaaaaeceeeeiiiinoooooouuuuyyAaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIiIJijJjKkLlLlLlLlllNnNnNnnOoOoOoOEoeRrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzsfOoUuAaIiOoUuUuUuUuUuAaAEaeOo'));
$user = user::byLogin($username);
if (is_object($user)) {
    $parameters['profile'] = $username;
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
		return;
	}
}

$eqLogic->checkAndUpdateCmd('sender', trim($json["message"]["from"]["id"]));
$eqLogic->checkAndUpdateCmd('chat', trim($json["message"]["chat"]["id"]));
if (isset($json["message"]["text"])) {
    $eqLogic->checkAndUpdateCmd('text', $json["message"]["text"]);

    $cmd_user = $eqLogic->getCmd('action', $json["message"]["chat"]["id"]);
    if (!is_object($cmd_user)) {
    	if ($eqLogic->getConfiguration('isAccepting') == 1) {
    		$cmd_user = new telegramCmd();
    		$cmd_user->setLogicalId($json["message"]["chat"]["id"]);
    		$cmd_user->setIsVisible(1);
    		$cmd_user->setName($username . ' - ' . $json["message"]["chat"]["id"]);
    		$cmd_user->setConfiguration('interact',0);
    		$cmd_user->setConfiguration('chatid',$json["message"]["chat"]["id"]);
    		$cmd_user->setType('action');
    		$cmd_user->setSubType('message');
    		$cmd_user->setEqLogic_id($eqLogic->getId());
    	} else {
    		return;
    	}
    }
    if (isset($json["message"]["chat"]["title"])) {
    	$cmd_user->setConfiguration('title',$json["message"]["chat"]["title"]);
    	$cmd_user->save();
    } else {
        if (isset($json["message"]["from"]["username"])) {
        	$cmd_user->setConfiguration('username',$json["message"]["from"]["username"]);
        	$cmd_user->save();
        }
        if (isset($json["message"]["from"]["first_name"])) {
            $cmd_user->setConfiguration('last_name',$json["message"]["from"]["first_name"]);
        }
        if (isset($json["message"]["from"]["last_name"])) {
            $cmd_user->setConfiguration('last_name',$json["message"]["from"]["last_name"]);
        }
    }
    $cmd_user->save();

    if (isset($json["message"]["reply_to_message"])) {
    	return;
    }

    if ($cmd_user->getConfiguration('interact') == 1) {
    	$reply['reply'] = interactQuery::tryToReply(trim($json["message"]["text"]), $parameters);
    } else {
    	$reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu');
    }
    $file_id = '';
}
if (isset($json["message"]["document"])) {
    $file_id = $json["message"]["document"]["file_id"];
    $file_name = $json["message"]["document"]["file_name"];
    $reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Document)';
}
if (isset($json["message"]["photo"])) {
    $file_id = $json["message"]["photo"]["file_id"];
    $file_name = $username . '.png';
    $reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Photo)';
}
if (isset($json["message"]["video"])) {
    $file_id = $json["message"]["video"]["file_id"];
    $file_name = $username . '.mp4';
    $reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Vidéo)';
}
if (isset($json["message"]["location"])) {
    $file_id = '';
    $cmd_user = $eqLogic->getCmd('action', $json["message"]["chat"]["id"]);
    if (is_object($cmd_user)) {
        $geoloc = str_replace('#','',$cmd_user->getConfiguration('cmdgeoloc', ''));
        //log::add('telegram', 'debug', $geoloc);
        $geolocCmd = geolocCmd::byId($geoloc);
        if (is_object($geolocCmd)) {
            $geolocCmd->event($json["message"]["location"]["latitude"] . ',' . $json["message"]["location"]["longitude"]);
            $geolocCmd->save();
        }
    }
    $reply['reply'] = $eqLogic->getConfiguration('reply', 'Message recu') . ' (Localisation)';
}

$answer = array('method' => 'sendMessage', 'chat_id' => $json["message"]["chat"]["id"], "reply_to_message_id" => $json["message"]["message_id"], "text" => $reply['reply']);
header("Content-Type: application/json");
echo json_encode($answer);

if ($file_id != '' && $eqLogic->getConfiguration('savepath','') != '') {
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

return true;

?>

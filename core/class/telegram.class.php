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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class telegram extends eqLogic {
    public static function health() {
        $return = array();
        if (strpos(network::getNetworkAccess('external'),'https') !== false) {
            $https = true;
        } else {
            $https = false;
        }
        $return[] = array(
            'test' => __('HTTPS', __FILE__),
            'result' => ($https) ?  __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => ($https) ? '' : __('Votre Jeedom ne permet pas le fonctionnement de Telegram sans HTTPS', __FILE__),
            'state' => $https,
        );
        return $return;
    }

    public function postSave() {
        $text = $this->getCmd(null, 'text');
        if (!is_object($text)) {
            $text = new telegramCmd();
            $text->setLogicalId('text');
            $text->setIsVisible(0);
            $text->setName(__('Message', __FILE__));
        }
        $text->setType('info');
        $text->setSubType('string');
        $text->setEqLogic_id($this->getId());
        $text->save();

        $sender = $this->getCmd(null, 'sender');
        if (!is_object($sender)) {
            $sender = new telegramCmd();
            $sender->setLogicalId('sender');
            $sender->setIsVisible(0);
            $sender->setName(__('Expediteur', __FILE__));
        }
        $sender->setType('info');
        $sender->setSubType('string');
        $sender->setEqLogic_id($this->getId());
        $sender->save();

        $sender = $this->getCmd(null, 'chat');
        if (!is_object($sender)) {
            $sender = new telegramCmd();
            $sender->setLogicalId('chat');
            $sender->setIsVisible(0);
            $sender->setName(__('Chat', __FILE__));
        }
        $sender->setType('info');
        $sender->setSubType('string');
        $sender->setEqLogic_id($this->getId());
        $sender->save();

        $alluser = $this->getCmd(null, 'alluser');
        if (!is_object($alluser)) {
            $alluser = new telegramCmd();
            $alluser->setLogicalId('alluser');
            $alluser->setIsVisible(1);
            $alluser->setName(__('Tous', __FILE__));
            $alluser->setType('action');
            $alluser->setConfiguration('chatid','Tous les utilisateurs');
            $alluser->setConfiguration('firstname','Tous les utilisateurs');
            $alluser->setConfiguration('username','Tous les utilisateurs');
            $alluser->setSubType('message');
            $alluser->setEqLogic_id($this->getId());
            $alluser->save();
        }

        $url = network::getNetworkAccess('external') . '/plugins/telegram/core/api/jeeTelegram.php?apikey=' . jeedom::getApiKey('telegram') . '&id=' . $this->getId();
        $token = trim($this->getConfiguration('bot_token'));

        $request_http = new com_http('https://api.telegram.org/bot' . $token . '/setWebhook');
        log::add('telegram', 'debug', $url);
        $post = array(
            'url' => $url
        );

        $request_http->setPost($post);
        try {
            $result = $request_http->exec(60, 1);
        } catch (Exception $e) {
        }
        log::add('telegram', 'debug', $result);

    }

}

class telegramCmd extends cmd {

    public function preSave() {
        if ($this->getSubtype() == 'message') {
            $this->setDisplay('title_disable', 0);
        }
    }

    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        $toSend = array();
        if ($this->getLogicalId() == 'alluser'){
            foreach($eqLogic->getCmd('action') as $cmd){
                if ($cmd->getLogicalId() != 'alluser'){
                    $toSend[]= $cmd->getConfiguration('chatid');
                }
            }
        } else {
            $toSend[] = $this->getConfiguration('chatid');
        }
        $request_http = "https://api.telegram.org/bot" . trim($eqLogic->getConfiguration('bot_token'));

        foreach ($toSend as $chatid){
            $data = array(
                'chat_id' => $chatid
            );
            $data['chat_id'] = $chatid;
            if ($eqLogic->getConfiguration('silentnotif') == true) {
                $data['disable_notification'] = 1;
            }
            if (isset($_options['answer'])) {
                $replyMarkup = array(
                    'keyboard' => array(
                        $_options['answer']
                    ),
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true
                );
                $encodedMarkup = json_encode($replyMarkup);
                //$data['reply_markup'] = '{"keyboard": [["' . implode('""],["', $_options['answer']) . '"]],"one_time_keyboard": true}';
                $data['reply_markup'] = $encodedMarkup;
                log::add('telegram', 'debug', $data['reply_markup']);
            }

            if (!isset($_options['files']) || !is_array($_options['files'])) {
                if ($_options['title'] == 'tts') {
                    $data['voice'] = new CURLFile(realpath($_options['message']));
                    $url = $request_http . "/sendVoice";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Content-Type:multipart/form-data"
                    ));
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    $output = curl_exec($ch);
                    return;
                } else if ($_options['title'] == 'location') {
                    if (strrpos($_options['message'],'geoloc:') !== false) {
                        $geoloc = explode('geoloc:',$_options['message']);
                        $geolocCmd = geolocCmd::byId($geoloc[1]);
                        if ($geolocCmd->getConfiguration('mode') == 'fixe') {
                          $geolocval = $geolocCmd->getConfiguration('coordinate');
                        } else {
                          $geolocval = $geolocCmd->execCmd();
                        }
                    } else {
                        $geolocval = $_options['message'];
                    }
                    $coordinate = explode(',',$geolocval);
                    $data['latitude'] = $coordinate[0];
                    $data['longitude'] = $coordinate[1];
                    $url = $request_http . "/sendLocation";
                } else {
                    $data['text'] = trim($_options['title'] . ' ' . $_options['message']);
                    $data['parse_mode'] = 'HTML';
                    $url = $request_http . "/sendMessage";
                }

                $options = array(
                    'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'content' => http_build_query($data),
                    ),
                );
                log::add('telegram', 'debug', print_r($data));
                $context  = stream_context_create($options);
                $result = file_get_contents($url, false, $context);
            }
            //log::add('telegram', 'debug', print_r($result));

            if (isset($_options['files']) && is_array($_options['files'])) {
                foreach ($_options['files'] as $file) {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    if ($ext == 'mp4'){
                        copy($file , substr($file,0,-3) . 'mkv');
                        $file = substr($file,0,-3) . 'mkv';
                    }
                    $photolist = "gif,jpeg,jpg,png";
                    $videolist = "avi,mpeg,mpg,mkv,mp4,mpe";
                    $audiolist = "ogg,mp3";
                    if (strpos($photolist,$ext) !== false) {
                        $post_fields = array('chat_id'   => $chatid,
                        'photo'     => new CURLFile(realpath($file)),
                        'caption' => $text
                        );
                        $url = $request_http . "/sendPhoto?chat_id=" . $chatid;
                    } else if (strpos($audiolist,$ext) !== false) {
                        $post_fields = array('chat_id'   => $chatid,
                        'audio'     => new CURLFile(realpath($file)),
                        'title' => $text
                        );
                        $url = $request_http . "/sendAudio";
                    } else if (strpos($videolist,$ext) !== false) {
                        $post_fields = array('chat_id'   => $chatid,
                        'video'     => new CURLFile(realpath($file)),
                        'caption' => $text
                        );
                        $url = $request_http . "/sendVideo";
                    } else {
                        $post_fields = array('chat_id'   => $chatid,
                        'document'     => new CURLFile(realpath($file)),
                        'caption' => $text
                        );
                        $url = $request_http . "/sendDocument";
                    }

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Content-Type:multipart/form-data"
                    ));
                    log::add('telegram', 'debug',$url);
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                    $output = curl_exec($ch);
                    if ($ext == 'mp4'){
                        unlink($file);
                    }
            }
        }
    }
}

}

?>

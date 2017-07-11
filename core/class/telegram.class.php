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
            $alluser->setDisplay('title_placeholder','option');
            $alluser->setDisplay('message_placeholder','message');
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

    public function sendTelegram($url,$type,$post_fields) {
        $ch = curl_init();
        if ($type == 'file') {
            $header = "Content-Type:multipart/form-data";
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            $header
        ));
        }/* else {
            $header = "Content-Type:application/x-www-form-urlencoded";
        }*/

        log::add('telegram', 'debug',$url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        //curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $output = curl_exec($ch);
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
                    if (is_file(realpath($_options['message']))) {
                        $data['voice'] = new CURLFile(realpath($_options['message']));
                    } else {
                        exec("pico2wave -l fr-FR -w /tmp/voice.wav \"" . $_options['message'] . "\"");
                        exec("opusenc --bitrate 64 /tmp/voice.wav /tmp/voice.ogg");
                        $data['voice'] = new CURLFile(realpath('/tmp/voice.ogg'));
                    }
                    $url = $request_http . "/sendVoice";
                    $this->sendTelegram($url,'file',$data);
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
                    //log::add('telegram', 'debug', print_r($data, true));
                    $this->sendTelegram($url,'message',$data);
                } else if ($_options['title'] == 'file') {
                    $_options['files'][0] = $_options['message'];
                } else {
                    $data['text'] = trim($_options['message']);
                    $data['parse_mode'] = 'HTML';
                    $url = $request_http . "/sendMessage";
                    //log::add('telegram', 'debug', print_r($data, true));
                    $this->sendTelegram($url,'message',$data);
                }
            }
            //log::add('telegram', 'debug', print_r($result, true));

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
                        'text' => trim($_options['message']),
                        'photo'     => new CURLFile(realpath($file)),
                        'caption' => pathinfo($file, PATHINFO_FILENAME)
                        );
                        $url = $request_http . "/sendPhoto?chat_id=" . $chatid;
                    } else if (strpos($audiolist,$ext) !== false) {
                        $post_fields = array('chat_id'   => $chatid,
                        'text' => trim($_options['title'] . ' ' . $_options['message']),
                        'audio'     => new CURLFile(realpath($file)),
                        'title' => pathinfo($file, PATHINFO_FILENAME)
                        );
                        $url = $request_http . "/sendAudio";
                    } else if (strpos($videolist,$ext) !== false) {
                        $post_fields = array('chat_id'   => $chatid,
                        'text' => trim($_options['title'] . ' ' . $_options['message']),
                        'video'     => new CURLFile(realpath($file)),
                        'caption' => pathinfo($file, PATHINFO_FILENAME)
                        );
                        $url = $request_http . "/sendVideo";
                    } else {
                        $post_fields = array('chat_id'   => $chatid,
                        'text' => trim($_options['title'] . ' ' . $_options['message']),
                        'document'     => new CURLFile(realpath($file)),
                        'caption' => pathinfo($file, PATHINFO_FILENAME)
                        );
                        $url = $request_http . "/sendDocument";
                    }
                    $this->sendTelegram($url,'file',$post_fields);
                    if ($ext == 'mp4'){
                        unlink($file);
                    }
            }
        }
    }
}

}

?>

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

	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	public static function health() {
		$https = strpos(network::getNetworkAccess('external'), 'https') !== false;
		$return[] = array(
			'test' => __('HTTPS', __FILE__),
			'result' => ($https) ? __('OK', __FILE__) : __('NOK', __FILE__),
			'advice' => ($https) ? '' : __('Votre Jeedom ne permet pas le fonctionnement de Telegram sans HTTPS', __FILE__),
			'state' => $https,
		);
		return $return;
	}

	/*     * *********************Methode d'instance************************* */

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
			$alluser->setConfiguration('chatid', 'Tous les utilisateurs');
			$alluser->setConfiguration('firstname', 'Tous les utilisateurs');
			$alluser->setConfiguration('username', 'Tous les utilisateurs');
			$alluser->setSubType('message');
			$alluser->setEqLogic_id($this->getId());
			$alluser->setDisplay('title_placeholder', __('Options', __FILE__));
			$alluser->save();
		}
		$url = network::getNetworkAccess('external') . '/plugins/telegram/core/api/jeeTelegram.php?apikey=' . jeedom::getApiKey('telegram') . '&id=' . $this->getId();
		$token = trim($this->getConfiguration('bot_token'));
		$request_http = new com_http('https://api.telegram.org/bot' . $token . '/setWebhook');
		log::add('telegram', 'debug', $url);
		$post = array(
			'url' => $url,
		);
		$request_http->setPost($post);
		try {
			$result = $request_http->exec(60, 1);
		} catch (Exception $e) {
		}
		log::add('telegram', 'debug', $result);
	}

	/*     * **********************Getteur Setteur*************************** */

}

class telegramCmd extends cmd {

	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function sendTelegram($_url, $_type, $_to, $_data) {
		foreach ($_to as $chatid) {
			$_data['chat_id'] = $chatid;
			$request_http = new com_http($_url);
			if ($_type == 'file') {
				$request_http->setHeader(array("Content-Type:multipart/form-data"));
			}
			$request_http->setPost($_data);
			log::add('telegram', 'debug', 'Call url ' . $_url . ' with option ' . print_r($_data, true));
			$output = $request_http->exec(60);
			log::add('telegram', 'debug', 'Result : ' . $output);
			if (!is_json($output)) {
				throw new Exception(__('Erreur lors de l\'envoi telegram : ', __FILE__) . $output);
			}
			$result = json_decode($output, true);
			if (!$result['ok']) {
				throw new Exception(__('Erreur lors de l\'envoi telegram : ', __FILE__) . $output);
			}
		}
	}

	public function execute($_options = array()) {
		if ($this->getType() == 'info') {
			return;
		}
		$data = array();
		$options = arg2array($_options['title']);
		$eqLogic = $this->getEqLogic();
		$to = array();
		if ($this->getLogicalId() == 'alluser') {
			foreach ($eqLogic->getCmd('action') as $cmd) {
				if ($cmd->getLogicalId() != 'alluser') {
					$to[] = $cmd->getConfiguration('chatid');
				}
			}
		} else {
			$to[] = $this->getConfiguration('chatid');
		}
		$request_http = "https://api.telegram.org/bot" . trim($eqLogic->getConfiguration('bot_token'));
		$data['disable_notification'] = (isset($options['disable_notify'])) ? $options['disable_notify'] : $eqLogic->getConfiguration('disable_notify', 0);
		$data['parse_mode'] = (isset($options['parse_mode'])) ? $options['parse_mode'] : $eqLogic->getConfiguration('parse_mode', 'HTML');

		if (isset($_options['answer'])) {
			$data['disable_notification'] = 0;
			$data['reply_markup'] = json_encode(array(
				'keyboard' => array($_options['answer']),
				'one_time_keyboard' => true,
				'resize_keyboard' => true,
			));
		}

		if (isset($options['location'])) {
			if (strrpos($options['location'], '#') !== false) {
				$geolocCmd = geolocCmd::byId(str_replace('#', '', $options['location']));
				$geolocval = ($geolocCmd->getConfiguration('mode') == 'fixe') ? $geolocCmd->getConfiguration('coordinate') : $geolocCmd->execCmd();
			} else {
				$geolocval = $options['location'];
			}
			$coordinate = explode(',', $geolocval);
			$data['latitude'] = $coordinate[0];
			$data['longitude'] = $coordinate[1];
			$url = $request_http . "/sendLocation";
			$this->sendTelegram($url, 'message', $to, $data);
		}

		if (isset($options['tts'])) {
			if (is_file(realpath($options['tts']))) {
				$data['voice'] = new CURLFile(realpath($options['tts']));
			} else {
				exec("pico2wave -l fr-FR -w /tmp/voice.wav \"" . $options['tts'] . "\"");
				exec("opusenc --bitrate 64 /tmp/voice.wav /tmp/voice.ogg");
				$data['voice'] = new CURLFile(realpath('/tmp/voice.ogg'));
			}
			$url = $request_http . "/sendVoice";
			$this->sendTelegram($url, 'file', $to, $data);
		}

		if (isset($options['file'])) {
			$_options['files'] = explode(',', $options['file']);
		}

		if (!isset($_options['files']) && $_options['message'] != '') {
			$data['text'] = trim($_options['message']);
			$url = $request_http . "/sendMessage";
			$this->sendTelegram($url, 'message', $to, $data);
		}

		if (isset($_options['files']) && is_array($_options['files'])) {
			foreach ($_options['files'] as $file) {
				if (trim($file) == '') {
					continue;
				}
				$text = ($_options['message'] == '') ? pathinfo($file, PATHINFO_FILENAME) : $_options['message'];
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				if ($ext == 'mp4') {
					copy($file, substr($file, 0, -3) . 'mkv');
					$file = substr($file, 0, -3) . 'mkv';
				}
				if (in_array($ext, array('gif', 'jpeg', 'jpg', 'png'))) {
					$data['photo'] = new CURLFile(realpath($file));
					$data['caption'] = $text;
					$url = $request_http . '/sendPhoto';
				} else if (in_array($ext, array('ogg', 'mp3'))) {
					$data['audio'] = new CURLFile(realpath($file));
					$data['title'] = $text;
					$url = $request_http . '/sendAudio';
				} else if (in_array($ext, array('avi', 'mpeg', 'mpg', 'mkv', 'mp4', 'mpe'))) {
					$data['video'] = new CURLFile(realpath($file));
					$data['caption'] = $text;
					$url = $request_http . '/sendVideo';
				} else {
					$data['document'] = new CURLFile(realpath($file));
					$data['caption'] = $text;
					$url = $request_http . '/sendDocument';
				}
				$this->sendTelegram($url, 'file', $to, $data);
				if ($ext == 'mp4') {
					unlink($file);
				}
			}
		}
	}

	/*     * **********************Getteur Setteur*************************** */

}

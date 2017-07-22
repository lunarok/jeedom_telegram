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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function telegram_update() {
  foreach (eqLogic::byType('telegram', true) as $telegram) {
      foreach ($telegram->getCmd('action') as $cmd) {
          $cmd->setDisplay('title_disable', 0);
          $cmd->setDisplay('title_placeholder', __('Options', __FILE__));
    			$cmd->setDisplay('message_placeholder',__('Message', __FILE__));
          $cmd->save();
      }
      $telegram->save();
  }
}

?>

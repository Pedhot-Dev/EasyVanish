<?php

/**
 *
 *  _____         _ _           _   _____
 * |  __ \       | | |         | | |  __ \
 * | |__) |__  __| | |__   ___ | |_| |  | | _____   __
 * |  ___/ _ \/ _` | '_ \ / _ \| __| |  | |/ _ \ \ / /
 * | |  |  __/ (_| | | | | (_) | |_| |__| |  __/\ V /
 * |_|   \___|\__,_|_| |_|\___/ \__|_____/ \___| \_/
 *
 *
 * Copyright 2021 Pedhot-Dev
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *
 * @author PedhotDev
 * @link https://github.com/Pedhot-Dev/EasyVanish
 *
 */

namespace pedhot\easyvanish\tasks;

use pedhot\easyvanish\EasyVanish;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class InvisibleTask extends Task {

    /** @var Player */
    private $player;

    public function __construct(Player $player) {
        $this->player = $player;
    }

    public function onRun(int $currentTick) {
        $this->player->sendPopup(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-popup")));
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->hasPermission("easyvanish.see.player")) {
                $onlinePlayer->showPlayer($this->player);
                return;
            }
            $onlinePlayer->hidePlayer($this->player);
        }
    }

}
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

namespace pedhot\easyvanish\events;

use pedhot\easyvanish\EasyVanish;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Server;

class EventListener implements Listener {

    public function __construct() {
        Server::getInstance()->getPluginManager()->registerEvents($this, EasyVanish::getInstance());
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        EasyVanish::getInstance()->playersOnline[] = $player;

        foreach (Server::getInstance()->getOnlinePlayers() as $players) {
            if (in_array($players, EasyVanish::getInstance()->getVanishedPlayers())) {
                $entry = new PlayerListEntry();
                $entry->uuid = $players->getUniqueId();
                $pk = new PlayerListPacket();
                $pk->entries[] = $entry;
                $pk->type = PlayerListPacket::TYPE_REMOVE;
                $player->sendDataPacket($pk);
            }
        }

        if (isset(EasyVanish::getInstance()->playerVanishData[$player->getRawUniqueId()])) {
            EasyVanish::getInstance()->startInvisible($player);
        }
    }

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        unset(EasyVanish::getInstance()->playersOnline[array_search($player, EasyVanish::getInstance()->playersOnline)]);
        if (EasyVanish::getInstance()->isInvisible($player)) {
            EasyVanish::getInstance()->destroyInvisible($player);
        }
    }

    public function onQueryRegenerate(QueryRegenerateEvent $event) {
        $event->setPlayerList(EasyVanish::getInstance()->playersOnline);
        foreach(Server::getInstance()->getOnlinePlayers() as $p) {
            if (in_array($p, EasyVanish::getInstance()->getVanishedPlayers())) {
                $event->setPlayerCount($event->getPlayerCount() - 1);
            }
        }
    }

}
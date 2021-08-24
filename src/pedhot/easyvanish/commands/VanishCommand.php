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

namespace pedhot\easyvanish\commands;

use pedhot\easyvanish\EasyVanish;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\TranslationContainer;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class VanishCommand extends Command {

    public function __construct(string $name) {
        parent::__construct($name, "Vanish command", "/vanish help");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$this->testPermission($sender)) return;
        if (!$sender instanceof Player) {
            if(count($args) < 1) {
                throw new InvalidCommandSyntaxException();
            }
            switch (strtolower(implode(" ", $args))) {
                case "help":
                    $helps = ["help"=> "Displays list of vanish commands", "list"=> "Displays list of vanished players", "<player: target>"=> "Vanish other player"];
                    $title = TextFormat::GREEN . "Vanish commands\n";
                    foreach ($helps as $cmd => $desc) {
                        $title .= TextFormat::GRAY . "/vanish " . $cmd . " : " . $desc . "\n";
                    }
                    $sender->sendMessage($title);
                    break;
                case "list":
                    $title = TextFormat::BOLD . TextFormat::GREEN . "List vanished on this server: \n";
                    $i = 1;
                    foreach (EasyVanish::getInstance()->getVanishedPlayers() as $player) {
                        $title .= TextFormat::RED . $i . "). " . TextFormat::YELLOW . $player->getName() . "\n";
                        $i++;
                    }
                    $sender->sendMessage($title);
                    break;
                default:
                    $player = Server::getInstance()->getPlayer(implode(" ", $args));
                    if ($player instanceof Player) {
                        if (!EasyVanish::getInstance()->isInvisible($player)) {
                            EasyVanish::getInstance()->startInvisible($player);
                            $player->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled-other1", ["{PLAYER}"=>$sender->getName()])));
                            $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled-other2", ["{PLAYER}"=>$player->getDisplayName()])));
                            if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                                Server::getInstance()->broadcastMessage(EasyVanish::getInstance()->getSetting("quit-message"));
                            }
                        }else {
                            EasyVanish::getInstance()->destroyInvisible($player);
                            $player->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled-other1", ["{PLAYER}"=>$sender->getName()])));
                            $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled-other2", ["{PLAYER}"=>$player->getDisplayName()])));
                            if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                                Server::getInstance()->broadcastMessage(EasyVanish::getInstance()->getSetting("join-message"));
                            }
                        }
                    }else{
                        $sender->sendMessage(new TranslationContainer("commands.generic.player.notFound"));
                    }
                    break;
            }
        }
        if(count($args) < 1 || empty($args)) {
            if ($sender->hasPermission("easyvanish.cmd.vanish")) {
                if (!EasyVanish::getInstance()->isInvisible($sender)) {
                    EasyVanish::getInstance()->startInvisible($sender);
                    $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled")));
                    if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                        Server::getInstance()->broadcastMessage(EasyVanish::getInstance()->getSetting("quit-message"));
                    }
                }else {
                    EasyVanish::getInstance()->destroyInvisible($sender);
                    $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled")));
                    if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                        Server::getInstance()->broadcastMessage(EasyVanish::getInstance()->getSetting("join-message"));
                    }
                }
            }else {
                $sender->sendMessage(Server::getInstance()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));
            }
            return;
        }
        switch (strtolower(implode(" ", $args))) {
            case "help":
                if (!$sender->hasPermission("easyvanish.cmd.vanish.help")) {
                    $sender->sendMessage(Server::getInstance()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));
                    return;
                }
                $helps = ["help"=> "Displays list of vanish commands", "list"=> "Displays list of vanished players", "<player: target>"=> "Vanish other player"];
                $title = TextFormat::GREEN . "Vanish commands\n";
                foreach ($helps as $cmd => $desc) {
                    $title .= TextFormat::GRAY . "/vanish " . $cmd . " : " . $desc . "\n";
                }
                $sender->sendMessage($title);
                break;
            case "list":
                if ($sender->hasPermission("easyvanish.cmd.vanish.list")) {
                    $title = TextFormat::BOLD . TextFormat::GREEN . "List vanished on this server: \n";
                    $i = 1;
                    foreach (EasyVanish::getInstance()->getVanishedPlayers() as $player) {
                        $title .= TextFormat::RED . $i . "). " . TextFormat::YELLOW . $player->getName() . "\n";
                        $i++;
                    }
                    $sender->sendMessage($title);
                }else {
                    $sender->sendMessage(Server::getInstance()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));
                }
                break;
            default:
                if (!$sender->hasPermission("easyvanish.cmd.vanish.other")) {
                    $sender->sendMessage(Server::getInstance()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));
                    return;
                }
                $player = Server::getInstance()->getPlayer(implode(" ", $args));
                if ($player instanceof Player) {
                    if (!EasyVanish::getInstance()->isInvisible($player)) {
                        EasyVanish::getInstance()->startInvisible($player);
                        $player->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled-other1", ["{PLAYER}"=>$sender->getName()])));
                        $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled-other2", ["{PLAYER}"=>$player->getDisplayName()])));
                        if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                            Server::getInstance()->broadcastMessage(EasyVanish::getInstance()->getSetting("quit-message"));
                        }
                    }else {
                        EasyVanish::getInstance()->destroyInvisible($player);
                        $player->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled-other1", ["{PLAYER}"=>$sender->getName()])));
                        $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled-other2", ["{PLAYER}"=>$player->getDisplayName()])));
                        if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                            Server::getInstance()->broadcastMessage(EasyVanish::getInstance()->getSetting("join-message"));
                        }
                    }
                }else{
                    $sender->sendMessage(new TranslationContainer("commands.generic.player.notFound"));
                }
                break;
        }
    }

}
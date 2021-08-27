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
use pedhot\easyvanish\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class VanishCommand extends Command implements PluginIdentifiableCommand {

    public function __construct(string $name) {
        parent::__construct($name, "Vanish command", "/vanish help");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$this->testPermission($sender)) return;
        if (!$sender instanceof Player) {
            if(count($args) < 1) {
                throw new InvalidCommandSyntaxException();
            }
            switch (strtolower($args[0])) {
                case "help":
                    $helps = ["help"=> "Displays list of vanish commands", "list"=> "Displays list of vanished players", "<player: target>"=> "Vanish other player"];
                    $title = TextFormat::GREEN . "Vanish commands\n";
                    foreach ($helps as $cmd => $desc) {
                        $title .= TextFormat::GRAY . "/vanish " . $cmd . " : " . $desc . "\n";
                    }
                    $sender->sendMessage($title);
                    break;
                case "list":
                    $playerNames = array_map(function (Player $player): string {
                        return TextFormat::YELLOW . $player->getName();
                    }, array_filter(EasyVanish::getInstance()->getVanishedPlayers(), function (Player $player): bool {
                        return $player->isOnline();
                    }));
                    sort($playerNames, SORT_STRING);
                    $sender->sendMessage(TextFormat::colorize($this->getMessage("vanish-list", ["{COUNT_VANISHED}"=>count($playerNames)])));
                    $sender->sendMessage(count($playerNames) >= 1 ? implode(TextFormat::RED . ", ", $playerNames) : TextFormat::colorize($this->getMessage("no-player-vanish")));
                    break;
                default:
                    $player = Server::getInstance()->getPlayer($args[0]);
                    if ($player instanceof Player) {
                        $this->vanish($sender, $player);
                    }else{
                        $sender->sendMessage(TextFormat::colorize($this->getMessage("player-not-found")));
                    }
                    break;
            }
            return;
        }
        if(count($args) < 1 || empty($args)) {
            if ($sender->hasPermission("easyvanish.cmd.vanish")) {
                $this->vanish($sender, $sender);
            }else {
                $sender->sendMessage(TextFormat::colorize($this->getMessage("no-perm", ["%a"=>"vanish"])));
            }
            return;
        }
        switch (strtolower($args[0])) {
            case "help":
                if (!$sender->hasPermission("easyvanish.cmd.vanish.help")) {
                    $sender->sendMessage(TextFormat::colorize($this->getMessage("no-perm", ["%a"=>"vanish help"])));
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
                    $playerNames = array_map(function (Player $player): string {
                        return TextFormat::YELLOW . $player->getName();
                    }, array_filter(EasyVanish::getInstance()->getVanishedPlayers(), function (Player $player): bool {
                        return $player->isOnline();
                    }));
                    sort($playerNames, SORT_STRING);
                    $sender->sendMessage(TextFormat::colorize($this->getMessage("vanish-list", ["{COUNT_VANISHED}"=>count($playerNames)])));
                    $sender->sendMessage(implode(TextFormat::RED . ", ", $playerNames));
                }else {
                    $sender->sendMessage(TextFormat::colorize($this->getMessage("no-perm", ["%a"=>"vanish list"])));
                }
                break;
            default:
                if (!$sender->hasPermission("easyvanish.cmd.vanish.other")) {
                    $sender->sendMessage(TextFormat::colorize($this->getMessage("no-perm", ["%a"=>"vanish other"])));
                    return;
                }
                $player = Server::getInstance()->getPlayer($args[0]);
                if ($player instanceof Player) {
                    $this->vanish($sender, $player);
                }else{
                    $sender->sendMessage(TextFormat::colorize($this->getMessage("player-not-found")));
                }
                break;
        }
    }

    /**
     * @param CommandSender $sender
     * @param Player $player
     */
    private function vanish(CommandSender $sender, Player $player) {
        if (!EasyVanish::getInstance()->isInvisible($player)) {
            EasyVanish::getInstance()->startInvisible($player);
            $player->setDisplayName(TextFormat::GRAY . "[V] " . TextFormat::RESET . $player->getDisplayName());
            $player->setNameTag(TextFormat::GRAY . "[V] " . TextFormat::RESET . $player->getNameTag());
            $player->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled-other1", ["{PLAYER}"=>$sender->getName()])));
            $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-enabled-other2", ["{PLAYER}"=>$player->getName()])));
            if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                Server::getInstance()->broadcastMessage(TextFormat::colorize(Utils::replaceVars(EasyVanish::getInstance()->getSetting("quit-message"), ["{PLAYER}"=> $player->getName()])));
            }
        }else {
            EasyVanish::getInstance()->destroyInvisible($player);
            $player->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled-other1", ["{PLAYER}"=>$sender->getName()])));
            $sender->sendMessage(TextFormat::colorize(EasyVanish::getInstance()->getMessage("vanish-disabled-other2", ["{PLAYER}"=>$player->getName()])));
            if (EasyVanish::getInstance()->getSetting("vanished-message")) {
                Server::getInstance()->broadcastMessage(TextFormat::colorize(Utils::replaceVars(EasyVanish::getInstance()->getSetting("join-message"), ["{PLAYER}"=> $player->getName()])));
            }
        }
    }

    public function getPlugin(): EasyVanish {
        return EasyVanish::getInstance();
    }

    /**
     * @param string $name
     * @param array $params
     * @param string|null $lang
     * @return string
     */
    private function getMessage(string $name, array $params = [], ?string $lang = null): string {
        return EasyVanish::getInstance()->getMessage($name, $params, $lang);
    }

}
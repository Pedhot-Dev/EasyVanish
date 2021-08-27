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

namespace pedhot\easyvanish;

use JackMD\ConfigUpdater\ConfigUpdater;
use pedhot\easyvanish\commands\VanishCommand;
use pedhot\easyvanish\events\EventListener;
use pedhot\easyvanish\singleton\SingletonTrait;
use pedhot\easyvanish\tasks\InvisibleTask;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class EasyVanish extends PluginBase {
    use SingletonTrait;

    /** @var TaskHandler[] */
    private $invisible;

    /** @var Player[] */
    public $playerVanishData = [];

    /** @var Player[] */
    private $players = [];

    /** @var Player[] */
    public $playersOnline = [];

    /** @var array */
    private $message = [];

    /** @var string */
    private $lang = "";

    public function onLoad() {
        $this->init();
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->getLogger()->debug("Checking configs");
        $this->checkConfigs();

        $this->getLogger()->debug("Checking virions");
        $this->checkVirions();

        $this->getLogger()->debug("Registering messages");
        $this->setLang(in_array($this->getConfig()->get("default-lang"), ["eng", "ind"]) ? $this->getConfig()->get("default-lang") : "eng");
        in_array($this->getConfig()->get("default-lang"), ["eng", "ind"]) ? $this->getConfig()->set("default-lang", $this->getConfig()->get("default-lang")) : $this->getConfig()->set("default-lang", "eng");
        $this->getConfig()->save();
        in_array($this->getConfig()->get("default-lang"), ["eng", "ind"]) ? $this->getLogger()->debug("Lang set to " . $this->getConfig()->get("default-lang")) : $this->getLogger()->debug("Lang " . $this->getConfig()->get("default-lang") . " not found!. set default lang to eng");
        $this->setupMessage();

        $this->getLogger()->debug("Registering commands");
        Server::getInstance()->getCommandMap()->register("EasyVanish", new VanishCommand("vanish"));

        $this->getLogger()->debug("Registering listeners");
        new EventListener();
    }

    /**
     * @param string $rawId
     * @return Player|null
     */
    public function getPlayerByRawId(string $rawId): ?Player {
        foreach ($this->players as $id => $player) {
            if ($rawId === $id) {
                return $player;
            }
        }
        return null;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function isInvisible(Player $player): bool {
        return isset($this->players[$player->getRawUniqueId()]);
    }

    /**
     * @param Player $player
     */
    public function startInvisible(Player $player): void {
        $this->playerVanishData[$player->getRawUniqueId()] = $player;
        $this->players[$player->getRawUniqueId()] = $player;
        $this->invisible[$player->getRawUniqueId()] = $this->getScheduler()->scheduleRepeatingTask(new InvisibleTask($player), 20);
        unset(EasyVanish::getInstance()->playersOnline[array_search($player, EasyVanish::getInstance()->playersOnline)]);
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->hidePlayer($player);
        }
        $this->removeFromList($player);
    }

    /**
     * @param Player $player
     */
    public function destroyInvisible(Player $player): void {
        $player->setNameTag(str_replace(TextFormat::GRAY . "[V] ", null, $player->getNameTag()));
        $player->setDisplayName(str_replace(TextFormat::GRAY . "[V] ", null, $player->getDisplayName()));
        $this->getScheduler()->cancelTask($this->invisible[$player->getRawUniqueId()]->getTaskId());
        EasyVanish::getInstance()->playersOnline[] = $player;
        unset($this->invisible[$player->getRawUniqueId()]);
        unset($this->players[$player->getRawUniqueId()]);
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->showPlayer($player);
        }
        $this->addToList($player);
    }

    /**
     * @return Player[]
     */
    public function getVanishedPlayers(): array {
        return $this->players;
    }

    /**
     * @param string $name
     * @param array $params
     * @param string|null $lang
     * @return string
     */
    public function getMessage(string $name, array $params = [], ?string $lang = null): string {
        $lang = $lang ?? $this->getLang();
        if (empty($params)) {
            return $this->message[$lang][$name];
        }
        return Utils::replaceVars($this->message[$lang][$name], $params);
    }

    public function getSetting(string $name): string {
        return $this->getConfig()->getNested("settings." . $name);
    }

    /**
     * @param string $lang
     */
    public function setLang(string $lang): void {
        $this->lang = $lang;
    }

    /**
     * @return string
     */
    public function getLang(): string {
        return $this->lang;
    }

    /**
     * @param Player $player
     */
    public function addToList(Player $player) {
        Server::getInstance()->updatePlayerListData($player->getUniqueId(), $player->getId(), $player->getDisplayName(), $player->getSkin(), $player->getXuid());
    }

    /**
     * @param Player $player
     */
    public function removeFromList(Player $player) {
        Server::getInstance()->removePlayerListData($player->getUniqueId());
    }

    /**
     * @return int
     */
    private function setupMessage(): int {
        $total = 0;
        foreach ($this->getConfig()->get("message") as $lang => $value) {
            $this->message[$lang] = $value;
            $total++;
        }
        return $total;
    }

    private function checkConfigs(): void {
        ConfigUpdater::checkUpdate($this, $this->getConfig(), "config-version", 1.0);
    }

    private function checkVirions(): void {
        if (!class_exists(ConfigUpdater::class)) {
            $this->getLogger()->error("ConfigUpdater virion not found download EasyVanish on poggit or download ConfigUpdater with DEVirion (not recommended)");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

}
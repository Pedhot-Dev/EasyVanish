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
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;

class EasyVanish extends PluginBase {
    use SingletonTrait;

    /** @var TaskHandler[] */
    private $invisible;

    /** @var Player[] */
    private $players = [];

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
        in_array($this->getConfig()->get("default-lang"), ["eng", "ind"]) ? $this->getLogger()->info("Lang set to " . $this->getConfig()->get("default-lang")) : $this->getLogger()->critical("Lang " . $this->getConfig()->get("default-lang") . " not found!. set default lang to eng");
        $this->setupMessage();

        $this->getLogger()->debug("Registering commands");
        Server::getInstance()->getCommandMap()->register("_cmd", new VanishCommand("vanish"));

        $this->getLogger()->debug("Registering listeners");
        new EventListener();
    }

    /**
     * @param string $xuidStr
     * @return Player|null
     */
    public function getPlayerByXuid(string $xuidStr): ?Player {
        foreach ($this->players as $xuid => $player) {
            if ($xuidStr === $xuid) {
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
        return isset($this->players[$player->getXuid()]);
    }

    /**
     * @param Player $player
     */
    public function startInvisible(Player $player): void {
        $this->players[$player->getXuid()] = $player;
        $this->invisible[$player->getXuid()] = $this->getScheduler()->scheduleRepeatingTask(new InvisibleTask($player), 20);
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->hidePlayer($player);
        }
        $this->removeFromList($player);
    }

    /**
     * @param Player $player
     */
    public function destroyInvisible(Player $player): void {
        $this->getScheduler()->cancelTask($this->invisible[$player->getXuid()]->getTaskId());
        unset($this->invisible[$player->getXuid()]);
        unset($this->players[$player->getXuid()]);
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
    private function addToList(Player $player) {
        foreach (Server::getInstance()->getOnlinePlayers() as $p){
            $pk = new PlayerListPacket();
            $pk->type = PlayerListPacket::TYPE_ADD;
            $pk->entries[] = PlayerListEntry::createAdditionEntry($player->getUniqueId(), $player->getId(), $player->getDisplayName(), SkinAdapterSingleton::get()->toSkinData($player->getSkin()), $player->getXuid());
            $p->sendDataPacket($pk);
        }
    }

    /**
     * @param Player $player
     */
    private function removeFromList(Player $player) {
        foreach (Server::getInstance()->getOnlinePlayers() as $p){
            $entry = new PlayerListEntry();
            $entry->uuid = $player->getUniqueId();
            $pk = new PlayerListPacket();
            $pk->entries[] = $entry;
            $pk->type = PlayerListPacket::TYPE_REMOVE;
            $p->sendDataPacket($pk);
        }
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
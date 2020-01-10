<?php

/**
 *                      __
 * __  __ /\/\   ___   / /  __ _ _ __   __ _
 * \ \/ //    \ / _ \ / /  / _` | '_ \ / _` |
 *  >  </ /\/\ \ (_) / /__| (_| | | | | (_| |
 * /_/\_\/    \/\___/\____/\__,_|_| |_|\__, |
 *                                     |___/
 *
 * Copyright (C) CortexPE 2019
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace CortexPE\xMoLang;


use CortexPE\xMoLang\behaviorpack\BehaviorPack;
use CortexPE\xMoLang\behaviorpack\ZippedBehaviorPack;
use CortexPE\xMoLang\event\PlayerScriptEvent;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ScriptCustomEventPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use ReflectionException;

class PacketInjector implements Listener {
	/** @var Main */
	protected $loader;

	public function __construct(Main $loader) {
		$this->loader = $loader;
	}

	/**
	 * @param DataPacketSendEvent $ev
	 *
	 * @priority LOWEST
	 */
	public function onPacketSend(DataPacketSendEvent $ev): void {
		foreach ($ev->getPackets() as $_ => $pk) {
			if ($pk instanceof StartGamePacket) {
				$pk->gameRules["experimentalgameplay"] = [1, true];
			} elseif ($pk instanceof ResourcePacksInfoPacket) {
				$mgr = $this->loader->getBehaviorPackManager();
				$pk->behaviorPackEntries = array_map(function (ZippedBehaviorPack $pack): ResourcePackInfoEntry {
					return new ResourcePackInfoEntry($pack->getPackId(), $pack->getPackVersion(), $pack->getPackSize(), "", "", "", $pack->hasClientScripts());
				}, $mgr->getBehaviorPacks());
				$pk->hasScripts = $mgr->hasClientScripts();
			} elseif ($pk instanceof ResourcePackStackPacket) {
				$mgr = $this->loader->getBehaviorPackManager();
				$pk->behaviorPackStack = array_map(function (ZippedBehaviorPack $pack): ResourcePackStackEntry {
					return new ResourcePackStackEntry($pack->getPackId(), $pack->getPackVersion(), "");
				}, $mgr->getBehaviorPacks());
				$pk->isExperimental = true;
			}
		}
	}
	/**
	 * @param DataPacketReceiveEvent $ev
	 *
	 * @priority LOWEST
	 * @throws ReflectionException
	 */
	public function onPacketReceive(DataPacketReceiveEvent $ev): void {
		$pk = $ev->getPacket();
		if($pk instanceof ScriptCustomEventPacket) {
			$eventName = $pk->eventName;
			$eventData = json_decode($pk->eventData);
			$ev = new PlayerScriptEvent($ev->getOrigin()->getPlayer(), $eventName, $eventData);
			$ev->call();
		} elseif($pk instanceof ResourcePackClientResponsePacket && $pk->status == ResourcePackClientResponsePacket::STATUS_SEND_PACKS) {
			$manager = $this->loader->getBehaviorPackManager();
			$provided = [];
			foreach($pk->packIds as $uuid) {
				$pack = $manager->getPackById(substr($uuid, 0,
					strpos($uuid, "_"))); //dirty hack for mojang's dirty hack for versions
				if($pack instanceof BehaviorPack) {
					$ev->getOrigin()->sendDataPacket(ResourcePackDataInfoPacket::create(
							$pack->getPackId(),
							1048576,
							(int) ceil($pack->getPackSize() / 1048576),
							$pack->getPackSize(),
							$pack->getSha256()
					));
					$provided[] = $uuid;
				}
			}
			// remove our behavior packs cuz PM doesnt know about it
			$pk->packIds = array_diff($pk->packIds, $provided);
		} elseif($pk instanceof ResourcePackChunkRequestPacket) {
			$manager = $this->loader->getBehaviorPackManager();
			$pack = $manager->getPackById($pk->packId);
			if($pack instanceof BehaviorPack) {
				$ev->getOrigin()->sendDataPacket(ResourcePackChunkDataPacket::create($pk->packId, $pk->chunkIndex, $pk->chunkIndex * 1048576, $pack->getPackChunk($pk->chunkIndex * 1048576, 1048576)));
				$ev->setCancelled(); // lets not let PM know about this
			}
		}
	}
}

<?php

declare(strict_types=1);


namespace CortexPE\xMoLang\network;


use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ScriptCustomEventPacket as PMScriptCustomEventPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;

class ScriptCustomEventPacket extends PMScriptCustomEventPacket implements ServerboundPacket, ClientboundPacket {
}
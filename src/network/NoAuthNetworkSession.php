<?php

declare(strict_types=1);
namespace gameparrot\gtipc\network;

use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\NetworkSession;

class NoAuthNetworkSession extends NetworkSession {
	public function setHandler(?PacketHandler $handler) : void {
		if ($handler instanceof LoginPacketHandler) {
			$refl = new \ReflectionClass(LoginPacketHandler::class);
			$authCb = $refl->getProperty("authCallback");
			$oldAuthCb = $authCb->getValue($handler);
			$authCb->setValue($handler, function(bool $_, bool $authRequired, Translatable|string|null $error, ?string $clientPubKey) use ($oldAuthCb) : void {
				$oldAuthCb(true, $authRequired, $error, $clientPubKey);
			});
		}
		parent::setHandler($handler);
	}
}

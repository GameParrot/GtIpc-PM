<?php

declare(strict_types=1);

namespace gameparrot\gtipc\listener;

use gameparrot\gtipc\conf\Conf;
use gameparrot\gtipc\raklib\GtIpcRakLibInterface;
use pocketmine\event\Listener;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\StandardEntityEventBroadcaster;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\network\query\DedicatedQueryNetworkInterface;
use pocketmine\plugin\PluginBase;
use function get_class;
use function method_exists;

class GtIpcListener implements Listener {
	public function __construct(private PluginBase $plugin, private Conf $conf) {
		$typeConverter = TypeConverter::getInstance();
		if (method_exists($typeConverter, "getProtocolId")) {
			$packetBroadcaster = new StandardPacketBroadcaster($plugin->getServer(), $typeConverter->getProtocolId()); // @phpstan-ignore-line
		} else {
			$packetBroadcaster = new StandardPacketBroadcaster($plugin->getServer()); // @phpstan-ignore-line
		}
		$entityEventBroadcaster = new StandardEntityEventBroadcaster($packetBroadcaster, $typeConverter);

		$interface = new GtIpcRakLibInterface($this->plugin->getServer(), $conf->socketPath, $conf->serverKey, $conf->isServer, $packetBroadcaster, $entityEventBroadcaster, $typeConverter, $conf->xboxAuthDisabled);
		$this->plugin->getServer()->getNetwork()->registerInterface($interface);
	}
	public function onInterfaceRegister(NetworkInterfaceRegisterEvent $event) : void {
		if (!$this->conf->blockDefaultInterfaces) {
			return;
		}
		$interface = $event->getInterface();
		if (!$interface instanceof GtIpcRakLibInterface && ($interface instanceof RakLibInterface || $interface instanceof DedicatedQueryNetworkInterface)) {
			$event->cancel();
			$this->plugin->getLogger()->info("Prevented " . get_class($event->getInterface()) . " from bring registered");
		}
	}
}

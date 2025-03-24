<?php

declare(strict_types=1);

namespace gameparrot\gtipc\event;

use pocketmine\event\server\NetworkInterfaceEvent;
use pocketmine\network\NetworkInterface;

class CustomPacketEvent extends NetworkInterfaceEvent {
	private string $packet;
	public function __construct(NetworkInterface $interface, string $packet) {
		parent::__construct($interface);
		$this->packet = $packet;
	}

	public function getPacket() : string {
		return $this->packet;
	}
}

<?php

declare(strict_types=1);

namespace gameparrot\gtipc\conf;

use pocketmine\utils\Config;

class Conf {
	// Path to unix socket
	public string $socketPath;
	// Key of server (only used when isServer is set to false; connecting to ipc server)
	public string $serverKey;
	// If true, serve ipc clients instead of connecting to server. Not recommended for proxy usage.
	public bool $isServer;
	// If true, disable xbox auth. Recommended if the ipc server is a proxy
	public bool $xboxAuthDisabled;
	// If true, block default raklib interfaces. Recommended if the ipc server is a proxy
	public bool $blockDefaultInterfaces;

	public function __construct(Config $conf) {
		$this->socketPath = $conf->get("Socket-Path", "/tmp/gtipc.sock");
		$this->serverKey = $conf->get("Server-Key", "default");
		$this->isServer = $conf->get("Is-Server", false);
		$this->xboxAuthDisabled = $conf->get("Xbox-Auth-Disabled", false);
		$this->blockDefaultInterfaces = $conf->get("Block-Default-Interfaces", true);
	}
}

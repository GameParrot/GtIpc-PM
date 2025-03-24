<?php

declare(strict_types=1);

namespace gameparrot\gtipc;

use gameparrot\gtipc\conf\Conf;
use gameparrot\gtipc\listener\GtIpcListener;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase {
	public function onEnable() : void {
		$this->saveDefaultConfig();
		$conf = new Conf($this->getConfig());
		$this->getServer()->getPluginManager()->registerEvents(new GtIpcListener($this, $conf), $this);
	}
}

<?php

declare(strict_types=1);
namespace gameparrot\gtipc\raklib;

use pocketmine\utils\Binary;
use function strlen;
use function substr;

class PacketDecoder {
	private string $buf = "";

	public function decodeFromString(string $buf) : array {
		$packets = [];

		$buf = $this->buf . $buf;

		$numRead = 0;
		$totalLen = strlen($buf);
		while ($numRead < $totalLen) {
			if (strlen($buf) < 4) {
				$this->buf = $buf;
				return $packets;
			}
			$len = Binary::readInt($buf);

			$numRead += $len + 4;
			if (strlen($buf) < $len + 4) {
				$this->buf = $buf;
				return $packets;
			}
			$buf = substr($buf, 4);

			$data = substr($buf, 0, $len);
			$this->buf = "";
			$packets[] = $data;

			$buf = substr($buf, $len);
		}

		return $packets;
	}
}

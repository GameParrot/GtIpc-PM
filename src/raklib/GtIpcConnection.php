<?php

declare(strict_types=1);

namespace gameparrot\gtipc\raklib;

use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\SnoozeAwarePthreadsChannelWriter;
use pocketmine\utils\Binary;
use function assert;
use function fclose;
use function fread;
use function fwrite;
use function strlen;
use function substr;
use function usleep;

class GtIpcConnection {
	private PacketDecoder $decoder;
	/**
	 * @param resource $socket
	 */
	public function __construct(private PthreadsChannelReader $reader, private SnoozeAwarePthreadsChannelWriter $writer, private $socket) {
		$this->decoder = new PacketDecoder();
	}

	public function tick() : void {
		$pk = "";
		while ($msg = $this->reader->read()) {
			$pk .= Binary::writeInt(strlen($msg)) . $msg;
		}
		if ($pk !== "") {
			self::fwrite_all($this->socket, $pk);
		}
		while ($msg = fread($this->socket, 65535)) {
			if ($msg === "") {
				break;
			}
			foreach ($this->decoder->decodeFromString($msg) as $pk) {
				if (strlen($pk) > 0) {
					$this->writer->write($pk);
				}
			}
		}
	}

	private static function fwrite_all($handle, string $data) : void {
		$original_len = strlen($data);
		if ($original_len > 0) {
			$len = $original_len;
			$written_total = 0;
			for ($i = 0 ;$i < 1000; $i++) {
				$written_now = fwrite($handle, $data);
				if ($written_now === $len) {
					return;
				}
				if ($written_now < 1) {
					usleep(1000);
				}
				$written_total += $written_now;
				$data = substr($data, $written_now);
				$len -= $written_now;
				// assert($len > 0);
				// assert($len === strlen($data));
			}
			assert($len === strlen($data));
		}
	}

	public function close() : void {
		fclose($this->socket);
	}
}

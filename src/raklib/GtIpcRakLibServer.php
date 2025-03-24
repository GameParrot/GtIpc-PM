<?php

declare(strict_types=1);

namespace gameparrot\gtipc\raklib;

use pmmp\thread\ThreadSafeArray;
use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\RakLibServer;
use pocketmine\network\mcpe\raklib\SnoozeAwarePthreadsChannelWriter;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use raklib\utils\InternetAddress;
use function assert;
use function fwrite;
use function microtime;
use function strlen;
use function substr;
use function time_sleep_until;
use function usleep;

class GtIpcRakLibServer extends RakLibServer {
	private const TPS = 2000;
	private const TIME_PER_TICK = 1 / self::TPS;

	public function __construct(
		ThreadSafeLogger $logger,
		ThreadSafeArray $mainToThreadBuffer,
		ThreadSafeArray $threadToMainBuffer,
		int $serverId,
		SleeperHandlerEntry $sleeperEntry,
		private string $socketPath,
		private string $serverKey,
		private bool $server,
	) {
		parent::__construct($logger, $mainToThreadBuffer, $threadToMainBuffer, new InternetAddress("", 0, 0), $serverId, 1400, 10, $sleeperEntry);
	}

	protected function onRun() : void {
		$reader = new PthreadsChannelReader($this->mainToThreadBuffer);
		$writer = new SnoozeAwarePthreadsChannelWriter($this->threadToMainBuffer, $this->sleeperEntry->createNotifier());
		$this->synchronized(function () : void {
			$this->ready = true;
			$this->notify();
		});
		$handler = new GtIpcHandler($this->server, $this->serverKey, $this->socketPath, $reader, $writer);
		while (!$this->isKilled) {
			$start = microtime(true);
			$handler->tick();
			$time = microtime(true) - $start;
			if ($time < self::TIME_PER_TICK) {
				@time_sleep_until(microtime(true) + self::TIME_PER_TICK);
			}
		}
		$handler->close();
	}

	private function fwrite_all($handle, string $data) : void {
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
}

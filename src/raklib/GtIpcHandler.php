<?php

declare(strict_types=1);

namespace gameparrot\gtipc\raklib;

use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\SnoozeAwarePthreadsChannelWriter;
use pocketmine\utils\Binary;
use function fclose;
use function file_exists;
use function fwrite;
use function stream_set_blocking;
use function stream_socket_accept;
use function stream_socket_client;
use function stream_socket_server;
use function strlen;
use function unlink;

class GtIpcHandler {
	private GtIpcConnection $connection;
	/** @var resource */
	private $socket;
	public function __construct(private bool $server, private string $key, private string $socketPath, private PthreadsChannelReader $reader, private SnoozeAwarePthreadsChannelWriter $writer) {
		if ($this->server) {
			if (file_exists($socketPath)) {
				unlink($socketPath);
			}
			$this->socket = stream_socket_server("unix://" . $socketPath);
		} else {
			$this->socket = stream_socket_client("unix://" . $socketPath);
			fwrite($this->socket, Binary::writeByte(strlen($this->key)) . $this->key);
			$this->connection = new GtIpcConnection($this->reader, $this->writer, $this->socket);
		}
		stream_set_blocking($this->socket, false);
	}

	public function tick() : void {
		if ($this->server) {
			try {
				if (!isset($this->connection) && $newConn = stream_socket_accept($this->socket, 0.01)) {
					stream_set_blocking($newConn, false);
					$this->connection = new GtIpcConnection($this->reader, $this->writer, $newConn);
				}
			} catch (\ErrorException $e) {
			}
		}
		if (isset($this->connection)) {
			try {
				$this->connection->tick();
			} catch (\ErrorException $e) {
				if (!$this->server) {
					throw $e;
				}
				$this->connection->close();
				unset($this->connection);
			}
		}
	}

	public function close() : void {
		if (isset($this->connection)) {
			$this->connection->close();
		}
		if ($this->server) {
			fclose($this->socket);
			unlink($this->socketPath);
		}
	}
}

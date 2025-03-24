<?php

declare(strict_types=1);

namespace gameparrot\gtipc\raklib;

use gameparrot\gtipc\event\CustomPacketEvent;
use gameparrot\gtipc\network\NoAuthNetworkSession;
use pmmp\thread\ThreadSafeArray;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\PthreadsChannelWriter;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\raklib\RakLibPacketSender;
use pocketmine\Server;
use pocketmine\timings\Timings;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\server\ipc\RakLibToUserThreadMessageReceiver;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use function mt_rand;
use const PHP_INT_MAX;

class GtIpcRakLibInterface extends RakLibInterface {
	private \ReflectionProperty $sessionsRefl;
	private \ReflectionProperty $networkRefl;

	private UserToRakLibThreadMessageSender $interface;

	public function __construct(
		private Server $server,
		string $path,
		string $key,
		bool $isServer,
		private PacketBroadcaster $packetBroadcaster,
		private EntityEventBroadcaster $entityEventBroadcaster,
		private TypeConverter $typeConverter,
		private bool $disableAuth,
	) {
		$refl = new \ReflectionClass(RakLibInterface::class);

		$this->sessionsRefl = $refl->getProperty("sessions");
		$this->networkRefl = $refl->getProperty("network");

		$refl->getProperty("server")->setValue($this, $server);

		$refl->getProperty("packetBroadcaster")->setValue($this, $packetBroadcaster);
		$refl->getProperty("entityEventBroadcaster")->setValue($this, $entityEventBroadcaster);
		$refl->getProperty("typeConverter")->setValue($this, $typeConverter);

		$rakServerId = mt_rand(0, PHP_INT_MAX);
		$refl->getProperty("rakServerId")->setValue($this, $rakServerId);

		/** @phpstan-var ThreadSafeArray<int, string> $mainToThreadBuffer */
		$mainToThreadBuffer = new ThreadSafeArray();
		/** @phpstan-var ThreadSafeArray<int, string> $threadToMainBuffer */
		$threadToMainBuffer = new ThreadSafeArray();

		$writer = new PthreadsChannelWriter($mainToThreadBuffer);
		$eventReceiver = new RakLibToUserThreadMessageReceiver(new PthreadsChannelReader($threadToMainBuffer));

		$refl->getProperty("eventReceiver")->setValue($this, $eventReceiver);

		$this->interface = new UserToRakLibThreadMessageSender($writer);

		$refl->getProperty("interface")->setValue($this, $this->interface);

		$sleeperEntry = $server->getTickSleeper()->addNotifier(function () use ($eventReceiver) : void {
			Timings::$connection->startTiming();
			try {
				while ($eventReceiver->handle($this))
				;
			} finally {
				Timings::$connection->stopTiming();
			}
		});
		$refl->getProperty("sleeperNotifierId")->setValue($this, $sleeperEntry->getNotifierId());

		$refl->getProperty("rakLib")->setValue($this, new GtIpcRakLibServer(
			$server->getLogger(),
			$mainToThreadBuffer,
			$threadToMainBuffer,
			$rakServerId,
			$sleeperEntry,
			$path,
			$key,
			$isServer,
		));
	}

	public function onPacketReceive(int $sessionId, string $packet) : void {
		if ($sessionId === -1) {
			if (CustomPacketEvent::hasHandlers()) {
				$ev = new CustomPacketEvent($this, $packet);
				$ev->call();
			}
			return;
		}
		parent::onPacketReceive($sessionId, $packet);
	}

	public function writeCustomPacket(string $packet) : void {
		$pk = new EncapsulatedPacket();
		$pk->buffer = $packet;
		$pk->reliability = PacketReliability::RELIABLE_ORDERED;
		$pk->orderChannel = 0;
		$pk->identifierACK = null;
		$this->interface->sendEncapsulated(-1, $pk, true);
	}

	public function onClientConnect(int $sessionId, string $address, int $port, int $clientID) : void {
		if (!$this->disableAuth) {
			parent::onClientConnect($sessionId, $address, $port, $clientID);
			return;
		}
		$session = new NoAuthNetworkSession(
			$this->server,
			$this->networkRefl->getValue($this)->getSessionManager(),
			PacketPool::getInstance(),
			new RakLibPacketSender($sessionId, $this),
			$this->packetBroadcaster,
			$this->entityEventBroadcaster,
			ZlibCompressor::getInstance(), //TODO: this shouldn't be hardcoded, but we might need the RakNet protocol version to select it
			$this->typeConverter,
			$address,
			$port
		);
		$sessions = $this->sessionsRefl->getValue($this);
		$sessions[$sessionId] = $session;
		$this->sessionsRefl->setValue($this, $sessions);
	}
}

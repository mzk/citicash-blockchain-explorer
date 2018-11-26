<?php declare(strict_types = 1);

namespace App\Models;

use Kdyby\Redis\RedisClient;
use Kdyby\Redis\RedisStorage;

class RedisStorageService
{

	/**
	 * @var string
	 */
	private $redisHost;

	/**
	 * @var int
	 */
	private $redisDatabase;

	public function __construct(string $redisHost, int $redisDatabase)
	{
		$this->redisHost = $redisHost;
		$this->redisDatabase = $redisDatabase;
	}

	public function getStorage(): RedisStorage
	{
		return new RedisStorage(new RedisClient($this->redisHost, null, $this->redisDatabase));
	}
}

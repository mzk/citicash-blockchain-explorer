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

	public function __construct(string $redis_host)
	{
		$this->redisHost = $redis_host;
	}

	public function getStorage(): RedisStorage
	{
		return new RedisStorage(new RedisClient($this->redisHost));
	}
}

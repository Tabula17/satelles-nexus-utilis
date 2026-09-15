<?php

namespace Tabula17\Satelles\Utilis\Cache;

use Redis;
use Tabula17\Satelles\Utilis\Config\RedisConfig;


/**
 * Class responsible for managing caching operations using a Redis backend.
 * Implements the CacheManagerInterface to provide caching functionality.
 */
class RedisStorage implements CacheManagerInterface
{
    private Redis $redis;

    /**
     * Constructor for setting up a Redis instance with configuration, prefix, and serialization options.
     *
     * @param RedisConfig $redisConfig Configuration object for connecting to the Redis server.
     * @param string $prefix Key prefix to be used for all Redis keys, default is 'roga-cache:'.
     * @param null|int $serializer Serialization option, default is Redis::SERIALIZER_PHP.
     *
     * @return void
     */
    public function __construct(
        RedisConfig $redisConfig,
        string      $prefix = 'roga-cache:',
        null|int    $serializer = Redis::SERIALIZER_PHP
    )
    {
        $this->redis = new Redis(array_filter($redisConfig->toArray()));
        if (!empty($prefix)) {
            if (!str_ends_with($prefix, ':')) {
                $prefix .= ':';
            }
            $this->redis->setOption(Redis::OPT_PREFIX, $prefix);
        }
        if ($serializer !== null) {
            $this->redis->setOption(Redis::OPT_SERIALIZER, $serializer);
        }
    }

    /**
     * Generates a normalized key string by replacing certain delimiters with colons and ensuring consistency.
     *
     * @param string $key The original key to be normalized.
     *
     * @return string The normalized key string with delimiters replaced by colons.
     */
    private function getKey(string $key): string
    {
        $keyPath = explode(':', str_replace(['\\', '/', '.', '|'], ':', $key));
        return implode(':', $keyPath);
    }

    public function get(string $key)
    {
        return $this->redis->get($this->getKey($key));
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->redis->set($this->getKey($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        $exists = $this->redis->exists($this->getKey($key));
        return $exists && $exists > 0;
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->getKey($key));
    }

    public function clear(): void
    {
        $this->redis->flushDB();
    }
}
<?php

namespace AppKit\Redis;

use AppKit\Redis\Internal\RedisConnection;

use AppKit\Client\AbstractClient;

class RedisClient extends AbstractClient {
    private $uri;

    function __construct(
        $log,
        $host     = '127.0.0.1',
        $port     = 6379,
        $password = null,
        $database = null
    ) {
        parent::__construct($log -> withModule(static::class));

        $this -> uri = "redis://$host:$port";
        if($database)
            $this -> uri .= "/$database";
        if($password)
            $this -> uri .= '?password=' . rawurlencode($password);
    }

    public function __call($name, $args) {
        $this -> ensureConnected();
        return $this -> connection -> $name(...$args);
    }

    protected function createConnection() {
        return new RedisConnection($this -> log, $this -> uri);
    }
}

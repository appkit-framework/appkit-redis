<?php

namespace AppKit\Redis;

use AppKit\Client\AbstractClient;

use Clue\React\Redis\Factory;

class RedisClient extends AbstractClient {
    private $uri;
    private $factory;

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

        $this -> factory = new Factory();
    }

    public function __call($name, $args) {
        return $this -> getConnection() -> $name(...$args);
    }

    protected function createConnection() {
        return new RedisConnection($this -> factory, $this -> uri);
    }
}

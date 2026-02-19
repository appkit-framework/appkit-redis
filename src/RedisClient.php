<?php

namespace AppKit\Redis;

use AppKit\Client\AbstractClient;

use Clue\React\Redis\Factory;

class RedisClient extends AbstractClient implements RedisClientInterface {
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

        $uri = "redis://$host:$port";
        if($database)
            $uri .= "/$database";
        // TODO: For redis-react v3
        // $uri .= '?idle=0';
        if($password)
            $uri .= '&password=' . rawurlencode($password);
        $this -> uri = $uri;

        $this -> factory = new Factory();
    }

    public function __call($command, $args) {
        return $this -> getConnection() -> $command(...$args);
    }

    public function command($command, ...$args) {
        return $this -> getConnection() -> command($command, ...$args);
    }

    protected function createConnection() {
        return new RedisConnection($this -> factory, $this -> uri);
    }
}

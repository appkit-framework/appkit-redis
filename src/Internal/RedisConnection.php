<?php

namespace AppKit\Redis\Internal;

use AppKit\Client\AbstractClientConnection;
use AppKit\Client\ClientConnectionException;
use function AppKit\Async\await;

use Throwable;
use Clue\React\Redis\Factory;

class RedisConnection extends AbstractClientConnection {
    private $uri;

    private $log;
    private $factory;
    private $connection;

    function __construct($log, $uri) {
        $this -> log = $log -> withModule(static::class);
        $this -> uri = $uri;
        $this -> factory = new Factory();
    }

    public function __call($name, $args) {
        try {
            return await($this -> connection -> $name(...$args));
        } catch(Throwable $e) {
            throw new ClientConnectionException(
                $e -> getMessage(),
                previous: $e
            );
        }
    }

    protected function doConnect() {
        $this -> connection = await($this -> factory -> createClient($this -> uri));
        $this -> connection -> once('close', function() {
            $this -> setClosed();
        });
    }

    protected function doDisconnect() {
        $this -> connection -> end();
    }
}

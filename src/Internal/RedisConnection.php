<?php

namespace AppKit\Redis\Internal;

use AppKit\Client\AbstractClientConnection;
use AppKit\Client\ClientConnectionException;
use function AppKit\Async\await;

use Throwable;

class RedisConnection extends AbstractClientConnection {
    private $factory;
    private $uri;

    private $connection;

    function __construct($factory, $uri) {
        $this -> factory = $factory;
        $this -> uri = $uri;
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

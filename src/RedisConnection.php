<?php

namespace AppKit\Redis;

use AppKit\Redis\Internal\RedisInterface;

use AppKit\Client\AbstractClientConnection;
use function AppKit\Async\await;

use Throwable;

class RedisConnection extends AbstractClientConnection implements RedisInterface {
    private $factory;
    private $uri;

    private $connection;

    function __construct($factory, $uri) {
        $this -> factory = $factory;
        $this -> uri = $uri;
    }

    public function __call($command, $args) {
        return $this -> command($command, ...$args);
    }

    public function command($command, ...$args) {
        $this -> ensureConnected();

        try {
            return await($this -> connection -> $command(...$args));
            // TODO: For redis-react v3
            // return await($this -> connection -> callAsync($command, ...$args));
        } catch(Throwable $e) {
            throw new RedisConnectionException(
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

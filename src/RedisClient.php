<?php

namespace AppKit\Redis;

use AppKit\StartStop\StartStopInterface;
use AppKit\Health\HealthIndicatorInterface;
use AppKit\Health\HealthCheckResult;
use AppKit\Async\Task;
use AppKit\Async\CanceledException;
use function AppKit\Async\async;
use function AppKit\Async\await;
use function AppKit\Async\delay;

use Throwable;
use Clue\React\Redis\Factory;
use React\Promise\Deferred;

class RedisClient implements StartStopInterface, HealthIndicatorInterface {
    private $log;
    private $uri;
    private $factory;
    private $isConnected = false;
    private $isStopping = false;
    private $connectTask;
    private $disconnectDeferred;
    private $client;

    function __construct(
        $log,
        $host     = '127.0.0.1',
        $port     = 6379,
        $password = null,
        $database = null
    ) {
        $this -> log = $log -> withModule(static::class);

        $this -> uri = "redis://$host:$port";
        if($database)
            $this -> uri .= "/$database";
        if($password)
            $this -> uri .= '?password=' . rawurlencode($password);

        $this -> factory = new Factory();
    }

    public function __call($name, $args) {
        if(! $this -> isConnected)
            throw new RedisClientException('Client is not connected');

        try {
            return await($this -> client -> $name(...$args));
        } catch(Throwable $e) {
            throw new RedisClientException(
                $e -> getMessage(),
                previous: $e
            );
        }
    }

    public function start() {
        $this -> connect();
    }

    public function stop() {
        $this -> isStopping = true;

        if($this -> connectTask -> getStatus() == Task::RUNNING) {
            $this -> log -> debug('Connect task running during stop, canceling');
            $this -> connectTask -> cancel() -> join();
        }

        if($this -> isConnected) {
            try {
                $this -> disconnect();
                $this -> log -> info('Disconnected from Redis server');
            } catch(Throwable $e) {
                $error = 'Failed to disconnect from Redis server';
                $this -> log -> error($error, $e);
                throw new RedisClientException(
                    $error,
                    previous: $e
                );
            }
        }
    }

    public function checkHealth() {
        return new HealthCheckResult($this -> isConnected);
    }

    private function connect() {
        $this -> log -> debug('Starting connect task');

        $this -> connectTask = new Task(function() {
            return $this -> connectRoutine();
        });

        try {
            $this -> connectTask -> run() -> await();
            $this -> log -> debug('Connect task completed');
        } catch(CanceledException $e) {
            $this -> log -> info('Connect task canceled');
        }
    }

    private function connectRoutine() {
        $retryAfter = null;

        while(true) {
            try {
                $this -> log -> debug('Trying to connect to Redis server');

                $this -> client = await($this -> factory -> createClient($this -> uri));
                $this -> client -> once('close', async(function() {
                   $this -> onConnectionClose();
                }));

                $this -> log -> info('Connected to Redis server');

                break;
            } catch(Throwable $e) {
                if(! $retryAfter)
                    $retryAfter = 1;
                else if($retryAfter == 1)
                    $retryAfter = 5;
                else if($retryAfter == 5)
                    $retryAfter = 10;

                $this -> log -> error(
                    'Failed to connect to Redis server',
                    [ 'retryAfter' => $retryAfter ],
                    $e
                );
                delay($retryAfter);
            }
        }

        $this -> isConnected = true;
    }

    private function disconnect() {
        $this -> isConnected = false;
        $this -> disconnectDeferred = new Deferred();
        $this -> client -> end();
        await($this -> disconnectDeferred -> promise());
    }

    private function onConnectionClose() {
        $this -> isConnected = false;

        if($this -> disconnectDeferred)
            $this -> disconnectDeferred -> resolve(null);

        if($this -> isStopping)
            return;

        $this -> log -> warning('Connection to Redis lost, reconnecting');
        $this -> connect();
    }
}

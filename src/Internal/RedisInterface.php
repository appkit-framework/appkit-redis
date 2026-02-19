<?php

namespace AppKit\Redis\Internal;

interface RedisInterface {
    public function __call($command, $args); // TODO: Replace with explicit command methods
    public function command($command, ...$args);
}

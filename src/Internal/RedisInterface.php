<?php

namespace AppKit\Redis\Internal;

interface RedisInterface {
    public function __call($command, $args);
}

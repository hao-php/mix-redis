<?php

namespace Haoa\MixRedis;

/**
 * Interface LoggerInterface
 * @package Haoa\MixRedis
 */
interface LoggerInterface
{

    public function trace(float $time, string $cmd, array $args, ?\Throwable $exception): void;

}
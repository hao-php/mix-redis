<?php

namespace Haoa\MixRedis;

/**
 * Class Pipeline
 * @package Haoa\MixRedis
 */
class Pipeline extends Multi
{

    /**
     * AbstractConnection constructor.
     * @param Driver $driver
     * @param LoggerInterface|null $logger
     */
    public function __construct(Driver $driver, ?LoggerInterface $logger, bool $watch = false)
    {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->watch = $watch;

        $this->__call('multi', [\Redis::PIPELINE]);
    }

}

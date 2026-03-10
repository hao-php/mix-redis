<?php

namespace Haoa\MixRedis\Pool;

use Haoa\MixRedis\Driver;
use Haoa\ObjectPool\AbstractObjectPool;

/**
 * Class ConnectionPool
 * @package Haoa\MixRedis\Pool
 */
class ConnectionPool extends AbstractObjectPool
{

    /**
     * 借用连接
     * @return Driver
     */
    public function borrow(): object
    {
        return parent::borrow();
    }

}

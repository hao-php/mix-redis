<?php

namespace Haoa\MixRedis;

use Haoa\ObjectPool\Exception\WaitTimeoutException;
use Haoa\MixRedis\Pool\ConnectionPool;
use Haoa\MixRedis\Pool\Dialer;

/**
 * Class Redis
 * @package Haoa\MixRedis
 */
class Redis implements ConnectionInterface
{

    use ScanTrait;

    /**
     * 主机
     * @var string
     */
    protected $host = '';

    /**
     * 端口
     * @var int
     */
    protected $port = 6379;

    /**
     * 密码
     * @var string
     */
    protected $password = '';

    /**
     * 数据库
     * @var int
     */
    protected $database = 0;

    /**
     * 全局超时
     * @var float
     */
    protected $timeout = 5.0;

    /**
     * 重连间隔
     * @var int
     */
    protected $retryInterval = 0;

    /**
     * 读取超时
     * phpredis >= 3.1.3
     * @var float
     */
    protected $readTimeout = -1;

    /**
     * 最大活跃数
     * "0" 为不限制，"-1" 等于cpu数量
     * @var int
     */
    protected $maxOpen = -1;

    /**
     * 最多可空闲连接数
     * "-1" 等于cpu数量
     * @var int
     */
    protected $maxIdle = -1;

    /**
     * 连接可复用的最长时间
     * "0" 为不限制
     * @var int
     */
    protected $maxLifetime = 0;

    /** @var int 最大空闲时间，单位秒，零表示不限 */
    protected $maxIdleTime = 0;

    /**
     * 等待新连接超时时间
     * "0" 为不限制
     * @var float
     */
    protected $waitTimeout = 0.0;

    /**
     * 连接池
     * @var ConnectionPool
     */
    protected $pool;

    /**
     * @var Driver
     */
    protected $driver;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Redis constructor.
     * @param string $host
     * @param int $port
     * @param string $password
     * @param int $database
     * @param float $timeout
     * @param int $retryInterval
     * @param float|int $readTimeout
     * @throws \RedisException
     */
    public function __construct(string $host, int $port = 6379, string $password = '', int $database = 0, float $timeout = 5.0, int $retryInterval = 0, float $readTimeout = -1)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
        $this->timeout = $timeout;
        $this->retryInterval = $retryInterval;
        $this->readTimeout = $readTimeout;

        $this->driver = new Driver(
            $this->host,
            $this->port,
            $this->password,
            $this->database,
            $this->timeout,
            $this->retryInterval,
            $this->readTimeout
        );
    }

    protected function createPool()
    {
        if ($this->driver) {
            $this->driver->close();
            $this->driver = null;
        }

        $this->pool = new ConnectionPool(
            new Dialer(
                $this->host,
                $this->port,
                $this->password,
                $this->database,
                $this->timeout,
                $this->retryInterval,
                $this->readTimeout
            ),
            $this->maxOpen,
            $this->maxIdle,
            $this->maxLifetime,
            $this->waitTimeout,
            0.1,
            $this->maxIdleTime
        );
    }

    /**
     * 启动连接池，末尾可选参数兼容原有调用。
     * @param int $maxOpen 最大连接数
     * @param int $maxIdle 最大空闲连接数
     * @param int $maxLifetime 最大生命周期，单位秒，零表示不限
     * @param float $waitTimeout 借用等待时间，单位秒，零表示不限
     * @param int $maxIdleTime 最大空闲时间，单位秒，非正数表示不限
     */
    public function startPool(int $maxOpen, int $maxIdle, int $maxLifetime = 0, float $waitTimeout = 0.0, int $maxIdleTime = 0)
    {
        $this->maxOpen = $maxOpen;
        $this->maxIdle = $maxIdle;
        $this->maxLifetime = $maxLifetime;
        $this->maxIdleTime = max(0, $maxIdleTime);
        $this->waitTimeout = $waitTimeout;
        $this->createPool();
    }

    /**
     * @param int $maxOpen
     */
    public function setMaxOpenConns(int $maxOpen)
    {
        if ($this->maxOpen == $maxOpen) {
            return;
        }
        $this->maxOpen = $maxOpen;
        $this->createPool();
    }

    /**
     * @param int $maxIdle
     */
    public function setMaxIdleConns(int $maxIdle)
    {
        if ($this->maxIdle == $maxIdle) {
            return;
        }
        $this->maxIdle = $maxIdle;
        $this->createPool();
    }

    /**
     * @param int $maxLifetime
     */
    public function setConnMaxLifetime(int $maxLifetime)
    {
        if ($this->maxLifetime == $maxLifetime) {
            return;
        }
        $this->maxLifetime = $maxLifetime;
        $this->createPool();
    }

    /**
     * @param float $waitTimeout
     */
    public function setPoolWaitTimeout(float $waitTimeout)
    {
        if ($this->waitTimeout == $waitTimeout) {
            return;
        }
        $this->waitTimeout = $waitTimeout;
        $this->createPool();
    }

    /**
     * 调整最大空闲时间，直接更新当前池，不重建连接。
     * @param int $maxIdleTime 最大空闲时间，单位秒，非正数表示不限
     */
    public function setConnMaxIdleTime(int $maxIdleTime): void
    {
        $this->maxIdleTime = max(0, $maxIdleTime);
        if ($this->pool) {
            $this->pool->setConnMaxIdleTime($this->maxIdleTime);
        }
    }

    /** 清理当前池中生命周期或空闲时间已到期的连接，未启用池时返回零。 */
    public function cleanupIdleConnections(): int
    {
        return $this->pool ? $this->pool->cleanupIdleConnections() : 0;
    }

    /**
     * @return array
     */
    public function poolStats(): array
    {
        if (!$this->pool) {
            return [];
        }
        return $this->pool->stats();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Borrow connection
     * @return Connection
     * @throws WaitTimeoutException
     */
    protected function borrow(): Connection
    {
        if ($this->pool) {
            $driver = $this->pool->borrow();
            $conn = new Connection($driver, $this->logger);
        } else {
            $conn = new Connection($this->driver, $this->logger);
        }
        return $conn;
    }

    /**
     * @param string ...$keys
     * @return Multi
     */
    public function watch(string ...$keys): Multi
    {
        return $this->borrow()->watch(...$keys);
    }

    /**
     * @return Multi
     */
    public function multi(): Multi
    {
        return $this->borrow()->multi();
    }

    /**
     * @return Pipeline
     */
    public function pipeline(): Pipeline
    {
        return $this->borrow()->pipeline();
    }

    /**
     * Call
     * @param $command
     * @param $arguments
     * @return mixed
     * @throws \RedisException
     */
    public function __call($command, $arguments)
    {
        return $this->borrow()->__call($command, $arguments);
    }

}

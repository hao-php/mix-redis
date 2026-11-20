> OpenMix 出品：[https://openmix.org](https://openmix.org/mix-php)

# Mix Redis

PHP Redis for use in multiple execution environments, with support for FPM, CLI, Swoole, WorkerMan, and optional
connection pool (coroutine)

可在各种环境中使用的 PHP Redis，支持 FPM、CLI、Swoole、WorkerMan，可选的连接池 (协程)


## Installation

```
composer require haoa/mix-redis
```

## Quick start

注意：[协程环境中，不可在并发请求中使用单例](https://openmix.org/mix-php/docs/3.0/#/zh-cn/instructions?id=%e5%8d%8f%e7%a8%8b%e5%8d%95%e4%be%8b%e5%ae%9e%e4%be%8b%e5%8c%96)

```php
$rds = new Haoa\MixRedis\Redis('127.0.0.1', 6379, 'password', 0);

$rds->set('foo', 'bar');
$value = $rds->get('foo');
```

## Start Pool

在 `Swoole` 协程环境中，启动连接池

```php
$maxOpen = 50;        // 最大开启连接数
$maxIdle = 20;        // 最大闲置连接数
$maxLifetime = 3600;  // 连接的最长生命周期
$waitTimeout = 0.0;   // 从池获取连接等待的时间, 0为一直等待
$rds->startPool($maxOpen, $maxIdle, $maxLifetime, $waitTimeout);
Swoole\Runtime::enableCoroutine(); // 必须放到最后，防止触发协程调度导致异常
```

连接池统计

```php
$rds->poolStats(); // array, fields: total, idle, active
```

## 清理到期空闲连接

需要 `haoa/object-pool ^1.1`。启动连接池时，第五个可选参数是最大空闲时间，单位秒，默认 `0` 表示不限制；原四参数调用保持兼容。

```php
$rds->startPool(50, 20, 3600, 10, 300);
$rds->setConnMaxIdleTime(60); // 动态调整当前池，不重建连接。
$removed = $rds->cleanupIdleConnections(); // 在协程内执行。
```

生命周期或空闲时间任一到期就回收，只关闭空闲连接，不立即补建；使用中的连接等归还后再检查生命周期。库不会自动注册定时器，由调用方在 Worker 或 CLI 协程中调度清理，并在退出时停止任务。

新增清理测试使用真实客户端和连接池、模拟物理连接，不连接外部服务，不执行 SQL。可以单独运行：

```bash
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/PoolCleanupTest.php
```

也可运行 `php vendor/bin/phpunit -c phpunit.unit.xml`，使用不加载业务服务配置的单元测试入口。

## Transaction Multi & Pipeline

Multi

事务块内的多条命令会按照先后顺序被放进一个队列当中，最后由exec命令原子性(atomic)地执行。

```php
$tx = $rds->multi();
$tx->set('foo', 'bar');
$tx->set('foo1', 'bar1');
$ret = $tx->exec();
```

Pipeline

客户端将执行的命令写入到缓冲中，最后由exec命令一次性发送给redis执行返回。

```php
$tx = $rds->pipeline();
$tx->set('foo', 'bar');
$tx->set('foo1', 'bar1');
$ret = $tx->exec();
```

## Transaction Watch

监听值的变化，如果执行时有变化则事务失败，无变化则事务成功。

```php
$tx = $rds->watch('foo');
$tx->incr('foo');
$ret = $tx->exec();
```

## Logger

日志记录器，配置后可打印全部SQL信息

```php
$db->setLogger($logger);
```

`$logger` 需实现 `Haoa\MixRedis\LoggerInterface`

```php
interface LoggerInterface
{
    public function trace(float $time, string $cmd, array $args, ?\Throwable $exception): void;
}
```

## License

Apache License Version 2.0, http://www.apache.org/licenses/

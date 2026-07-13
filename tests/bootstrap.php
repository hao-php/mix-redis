<?php

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    throw new \RuntimeException('Missing tests/config.php, copy tests/config.example.php and update it for your environment.');
}
require $configFile;

/**
 * @return \Haoa\MixRedis\Redis
 */
function redis()
{
    return new \Haoa\MixRedis\Redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE);
}

function swoole_co_run($func)
{
    $scheduler = new \Swoole\Coroutine\Scheduler;
    $scheduler->set([
        'hook_flags' => SWOOLE_HOOK_ALL,
    ]);
    $scheduler->add(function () use ($func) {
        call_user_func($func);
    });
    $scheduler->start();
}

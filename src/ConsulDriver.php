<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\ConfigConsul;

use Hyperf\ConfigCenter\AbstractDriver;
use Hyperf\Engine\Channel;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

class ConsulDriver extends AbstractDriver
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected $driverName = 'consul';

    protected $notifications = [];

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->client = $container->get(ClientInterface::class);
    }

    public function createMessageFetcherLoop(): void
    {
        $pullMode = $this->config->get('config_center.drivers.consul.pull_mode', PullMode::INTERVAL);
        if ($pullMode === PullMode::INTERVAL) {
            $this->handleIntervalLoop();
        }
    }

    protected function handleIntervalLoop(): void
    {
        $prevConfig = [];
        $this->loop(function () use (&$prevConfig) {
            $config = $this->pull();
            if ($config !== $prevConfig) {
                $this->syncConfig($config);
                $prevConfig = $config;
            }
        });
    }

    protected function loop(callable $callable, ?Channel $channel = null): int
    {
        return Coroutine::create(function () use ($callable, $channel) {
            $interval = $this->getInterval();
            retry(INF, function () use ($callable, $channel, $interval) {
                while (true) {
                    try {
                        $coordinator = CoordinatorManager::until(Constants::WORKER_EXIT);
                        $untilEvent = $coordinator->yield($interval);
                        if ($untilEvent) {
                            $channel && $channel->close();
                            break;
                        }
                        $callable();
                    } catch (\Throwable $exception) {
                        $this->logger->error((string) $exception);
                        throw $exception;
                    }
                }
            }, $interval * 1000);
        });
    }

    protected function pull(): array
    {
        return $this->client->pull();
    }

    protected function formatValue($value)
    {
        if (! $this->config->get('config_center.drivers.consul.strict_mode', false)) {
            return $value;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }

        if (is_numeric($value)) {
            $value = (strpos($value, '.') === false) ? (int) $value : (float) $value;
        }

        return $value;
    }

    protected function updateConfig(array $config)
    {
        echo json_encode($config);
        $mergedConfigs = [];
        foreach ($config as $c) {
            foreach ($c as $key => $value) {
                $mergedConfigs[$key] = $value;
            }
        }
        foreach ($mergedConfigs ?? [] as $key => $value) {
            $this->config->set($key, $this->formatValue($value));
            $this->logger->debug(sprintf('Config [%s] is updated', $key));
        }
    }
}

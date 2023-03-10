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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory as GuzzleClientFactory;
use Hyperf\Utils\Network;
use Psr\Container\ContainerInterface;

class ClientFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        /** @var \Hyperf\ConfigConsul\Option $option */
        $option = make(Option::class);
        $option->setServer($config->get('config_center.drivers.consul.server', 'http://127.0.0.1:8080'))
            ->setVersions($config->get('config_center.drivers.consul.versions', ''))
            ->setServiceName($config->get('config_center.drivers.consul.serviceName', ''))
            ->setClientIp($config->get('config_center.drivers.consul.client_ip', Network::ip()))
            ->setPullTimeout($config->get('config_center.drivers.consul.pull_timeout', 10))
            ->setIntervalTimeout($config->get('config_center.drivers.consul.interval_timeout', 60))
            ->setSecret($config->get('config_center.drivers.consul.secret', ''));
        $httpClientFactory = function (array $options = []) use ($container) {
            return $container->get(GuzzleClientFactory::class)->create($options);
        };
        return make(Client::class, compact('option', 'httpClientFactory'));
    }
}

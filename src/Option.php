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

use Hyperf\Utils\Str;

class Option
{
    /**
     * @var string
     */
    private $server = '';

    /**
     * @var string
     */
    private $versions = '';

    /**
     * @var string
     */
    private $serviceName = '';

    /**
     * @var string
     */
    private $clientIp = '127.0.0.1';

    /**
     * @var int
     */
    private $pullTimeout = 10;

    /**
     * @var int
     */
    private $intervalTimeout = 60;

    /**
     * @var string
     */
    private $secret;

    public function buildBaseUrl(): string
    {
        return implode('/', [
            $this->getServer(),
            $this->getVersions(),
            'kv',
            $this->getServiceName(),
        ]);
    }

    public function buildCacheKey(): string
    {
        return implode('+', [$this->getServiceName()]);
    }

    public function getServer(): string
    {
        return $this->server;
    }

    public function setServer(string $server): self
    {
        if (! Str::startsWith($server, ['http://', 'https://'])) {
            $server = 'http://' . $server;
        }
        $this->server = $server;
        return $this;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function setServiceName(string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    /**
     * @return string
     */
    public function getVersions(): string
    {
        return $this->versions;
    }

    /**
     * @param string $versions
     */
    public function setVersions(string $versions): self
    {
        $this->versions = $versions;
        return $this;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function setClientIp(string $clientIp): self
    {
        $this->clientIp = $clientIp;
        return $this;
    }

    public function getPullTimeout(): int
    {
        return $this->pullTimeout;
    }

    public function setPullTimeout(int $pullTimeout): self
    {
        $this->pullTimeout = $pullTimeout;
        return $this;
    }

    public function getIntervalTimeout(): int
    {
        return $this->intervalTimeout;
    }

    public function setIntervalTimeout(int $intervalTimeout): self
    {
        $this->intervalTimeout = $intervalTimeout;
        return $this;
    }

    public function setSecret(string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}

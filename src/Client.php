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

use Closure;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\Parallel;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Client implements ClientInterface
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var Option
     */
    protected $option;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @var Closure
     */
    protected $httpClientFactory;

    /**
     * @var \Hyperf\Contract\StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(
        Option $option,
        Closure $httpClientFactory,
        ConfigInterface $config,
        StdoutLoggerInterface $logger
    ) {
        $this->option = $option;
        $this->httpClientFactory = $httpClientFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function pull(): array
    {
        return $this->parallelPull();
    }

    public function getOption(): Option
    {
        return $this->option;
    }

    public function parallelPull(): array
    {
        $option = $this->option;
        $parallel = new Parallel();
        $httpClientFactory = $this->httpClientFactory;
        $parallel->add(function () use ($option, $httpClientFactory) {
            $client = $httpClientFactory();
            if (! $client instanceof \GuzzleHttp\Client) {
                throw new RuntimeException('Invalid http client.');
            }
            $cacheKey = $option->buildCacheKey();
            $releaseKey = $this->getReleaseKey($cacheKey);
            $query = [
                'keys' => '',
                'ip' => $option->getClientIp(),
                'releaseKey' => $releaseKey,
            ];
            $timestamp = $this->getTimestamp();
            $headers = [
                'Authorization' => $this->getAuthorization($timestamp, parse_url($option->buildBaseUrl(), PHP_URL_PATH) . '?' . http_build_query($query)),
                'Timestamp' => $timestamp,
            ];

            $response = $client->get($option->buildBaseUrl(), [
                'query' => $query,
                'headers' => $headers,
            ]);
            if ($response->getStatusCode() === 200 && strpos($response->getHeaderLine('content-type'), 'application/json') !== false) {
                $body = json_decode((string) $response->getBody(), true);
                $result = array();
                if(!empty($body)){
                    foreach ($body as $configName){
                        if(substr($configName,0,-1) != '/'){
                            $configQuery = [
                                'ip' => $option->getClientIp(),
                                'releaseKey' => $releaseKey,
                            ];
                            $configResponse = $client->get(trim($option->buildBaseUrl(), $option->getServiceName()).$configName, [
                                'query' => $configQuery,
                                'headers' => $headers,
                            ]);
                            $configBody = json_decode((string) $configResponse->getBody(), true);
                            if(isset($configBody[0]['Value'])){
                                $config = json_decode(base64_decode($configBody[0]['Value']), true);
                                if(!empty($config)){
                                    foreach ($config as $key => $value) {
                                        $result[$key] = $value;
                                    }
                                }
                            }
                        }
                    }
                }
                $this->cache[$cacheKey] = [
                    'releaseKey' => $body['releaseKey'] ?? '',
                    'configurations' => $result,
                ];
            } else {
                // Status code is 304 or Connection Failed, use the previous config value
                $result = $this->cache[$cacheKey]['configurations'] ?? [];
                if ($response->getStatusCode() !== 304) {
                    $this->logger->error('Connect to Consul server failed');
                }
            }
            return $result;
        });
        return $parallel->wait();
    }

    protected function getReleaseKey(string $cacheKey): ?string
    {
        return $this->cache[$cacheKey]['releaseKey'] ?? null;
    }

    private function hasSecret(): bool
    {
        return ! empty($this->option->getSecret());
    }

    private function getTimestamp(): string
    {
        [$usec, $sec] = explode(' ', microtime());
        return sprintf('%.0f', (floatval($usec) + floatval($sec)) * 1000);
    }

    private function getAuthorization(string $timestamp, string $pathWithQuery): string
    {
        if (! $this->hasSecret()) {
            return '';
        }
        $toSignature = $timestamp . "\n" . $pathWithQuery;
        $signature = base64_encode(hash_hmac('sha1', $toSignature, $this->option->getSecret(), true));
        return sprintf('Consul %s:%s', $this->option->getAppid(), $signature);
    }
}

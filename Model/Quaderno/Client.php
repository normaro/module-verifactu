<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Quaderno;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Log\Logger;
use Normaro\Verifactu\Model\Provider\Exception\PermanentException;
use Normaro\Verifactu\Model\Provider\Exception\TransientException;

/**
 * Low-level Quaderno API calls: HTTP Basic with the private key, pinned API version.
 */
class Client
{
    /** Quaderno sandbox: 20260309 returns 400 on POST /transactions */
    public const API_VERSION = '20241028';

    private const TIMEOUT = 30;

    /**
     * @param CurlFactory $curlFactory
     * @param Config $config
     * @param Json $json
     * @param Logger $logger
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Config $config,
        private readonly Json $json,
        private readonly Logger $logger
    ) {
    }

    /**
     * GET a path, return the decoded body.
     *
     * @param string $path relative to the account API URL, e.g. "invoices/123"
     * @param array<string,string> $query
     * @param int $storeId
     * @return array<mixed>
     * @throws TransientException
     * @throws PermanentException
     */
    public function get(string $path, array $query, int $storeId): array
    {
        $url = $this->url($path, $storeId) . ($query ? '?' . http_build_query($query) : '');
        $headers = $this->headers(false);
        $curl = $this->curl($storeId, $headers);
        $request = ['method' => 'GET', 'url' => $url, 'headers' => $this->loggedHeaders($headers), 'body' => null];
        $started = microtime(true);
        try {
            $curl->get($url);
        } catch (\Exception $e) {
            $this->logUnreachable($storeId, $request, $e, $started);
            throw new TransientException(__('Quaderno is not reachable: %1', $e->getMessage()), $e);
        }
        $this->logExchange($storeId, $request, $curl, $started);
        return $this->handle($curl, 'GET ' . $path);
    }

    /**
     * POST a JSON body, return the decoded response body.
     *
     * @param string $path
     * @param array<string,mixed> $body
     * @param int $storeId
     * @return array<mixed>
     * @throws TransientException
     * @throws PermanentException
     */
    public function post(string $path, array $body, int $storeId): array
    {
        $url = $this->url($path, $storeId);
        $headers = $this->headers(true);
        $curl = $this->curl($storeId, $headers);
        $json = (string)$this->json->serialize($body);
        $request = ['method' => 'POST', 'url' => $url, 'headers' => $this->loggedHeaders($headers), 'body' => $json];
        $started = microtime(true);
        try {
            $curl->post($url, $json);
        } catch (\Exception $e) {
            $this->logUnreachable($storeId, $request, $e, $started);
            throw new TransientException(__('Quaderno is not reachable: %1', $e->getMessage()), $e);
        }
        $this->logExchange($storeId, $request, $curl, $started);
        return $this->handle($curl, 'POST ' . $path);
    }

    /**
     * Request headers, without the Authorization header (added by setCredentials).
     *
     * @param bool $hasBody
     * @return array<string,string>
     */
    private function headers(bool $hasBody): array
    {
        $headers = [
            'Accept' => 'application/json; api_version=' . self::API_VERSION,
            'User-Agent' => 'normaro-module-verifactu',
        ];
        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    /**
     * Headers as logged: the API key is never written.
     *
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    private function loggedHeaders(array $headers): array
    {
        return $headers + ['Authorization' => 'Basic [not logged]'];
    }

    /**
     * Configured client.
     *
     * @param int $storeId
     * @param array<string,string> $headers
     * @return Curl
     * @throws PermanentException
     */
    private function curl(int $storeId, array $headers): Curl
    {
        $key = $this->config->getApiKey($storeId);
        if ($key === '') {
            throw new PermanentException(__('Verifactu: the Quaderno API key is not configured.'));
        }
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->setHeaders($headers);
        $curl->setCredentials($key, '');
        return $curl;
    }

    /**
     * Debug log of a completed call.
     *
     * @param int $storeId
     * @param array<string,mixed> $request
     * @param Curl $curl
     * @param float $started
     * @return void
     */
    private function logExchange(int $storeId, array $request, Curl $curl, float $started): void
    {
        if (!$this->logger->isDebug($storeId)) {
            return;
        }
        $headers = $curl->getHeaders();
        $lower = array_change_key_case($headers, CASE_LOWER);
        unset($headers['Set-Cookie'], $headers['set-cookie']);
        $this->logger->exchange($storeId, $request, [
            'status' => $curl->getStatus(),
            'x_request_id' => (string)($lower['x-request-id'] ?? ''),
            'headers' => $headers,
            'body' => (string)$curl->getBody(),
        ], (microtime(true) - $started) * 1000);
    }

    /**
     * Debug log of a call that got no response.
     *
     * @param int $storeId
     * @param array<string,mixed> $request
     * @param \Exception $error
     * @param float $started
     * @return void
     */
    private function logUnreachable(int $storeId, array $request, \Exception $error, float $started): void
    {
        $this->logger->exchange(
            $storeId,
            $request,
            ['status' => 0, 'error' => $error->getMessage()],
            (microtime(true) - $started) * 1000
        );
    }

    /**
     * Full URL; refuses a non-sandbox host when the environment is sandbox, and the reverse.
     *
     * @param string $path
     * @param int $storeId
     * @return string
     * @throws PermanentException
     */
    private function url(string $path, int $storeId): string
    {
        $base = $this->config->getApiUrl($storeId);
        $host = preg_match('#^https://([^/:?\#]+)#i', $base, $match) ? strtolower($match[1]) : '';
        if ($base === '' || $host === '' || !str_starts_with($base, 'https://')) {
            throw new PermanentException(__('Verifactu: the Quaderno API URL is missing or not https.'));
        }
        $isSandboxHost = str_ends_with($host, Config::SANDBOX_HOST_SUFFIX);
        $isSandbox = $this->config->getEnvironment($storeId) === Config::ENV_SANDBOX;
        if ($isSandbox !== $isSandboxHost) {
            throw new PermanentException(__(
                'Verifactu: environment "%1" does not match the API host %2.',
                $this->config->getEnvironment($storeId),
                $host
            ));
        }
        return $base . ltrim($path, '/');
    }

    /**
     * Map the HTTP status to a result or an exception.
     *
     * @param Curl $curl
     * @param string $call
     * @return array<mixed>
     * @throws TransientException
     * @throws PermanentException
     */
    private function handle(Curl $curl, string $call): array
    {
        $status = $curl->getStatus();
        $raw = (string)$curl->getBody();
        $body = $this->decode($raw);
        if ($status >= 200 && $status < 300) {
            return $body;
        }
        $message = $this->errorMessage($curl, $call, $raw, $body);
        if ($status === 0 || $status === 429 || $status >= 500) {
            throw new TransientException(__('%1', $message));
        }
        throw new HttpException(__('%1', $message), null, 0, $status, $body);
    }

    /**
     * Decoded JSON body, or [] if empty / not JSON.
     *
     * @param string $raw
     * @return array<mixed>
     */
    private function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Error text with the body and the x-request-id (for Quaderno support).
     *
     * @param Curl $curl
     * @param string $call
     * @param string $raw
     * @param array<mixed> $body
     * @return string
     */
    private function errorMessage(Curl $curl, string $call, string $raw, array $body): string
    {
        $headers = array_change_key_case($curl->getHeaders(), CASE_LOWER);
        $requestId = (string)($headers['x-request-id'] ?? '');
        $detail = $body ? (string)$this->json->serialize($body) : substr($raw, 0, 200);
        return trim(sprintf('%s: HTTP %d %s', $call, $curl->getStatus(), $detail))
            . ($requestId !== '' ? ' (x-request-id ' . $requestId . ')' : '');
    }
}

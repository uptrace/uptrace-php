<?php

declare(strict_types=1);

namespace Uptrace;

class Dsn {
    public string $dsn = '';
    public string $appEndpoint = '';
    public string $otlpEndpoint = '';

	private string $scheme = '';
	private string $host = '';
	private int $port = 14318;

	private string $projectID = '';
	private string $token = '';

    public function __construct($str) {
        $url = parse_url($str);
        if (!$url) {
            $msg = sprintf("can't parse DSN '%s'", $str);
            throw new InvalidArgumentException($msg);
        }

        $this->scheme = $url['scheme'];
        $this->host = $url['host'];
        $this->port = $url['port'] ?? 0;
        $this->projectID = $url['path'];
        $this->token = $url['user'];

        if ($this->host == 'api.uptrace.dev') {
            $this->host = 'uptrace.dev';
        }

        if ($this->host != 'uptrace.dev') {
            switch ($this->port) {
            case 4317:
            case 14317:
                echo sprintf(
                    'got port %d (OTLP/gRPC), but uptrace-php expects 14318 (OTLP/HTTP)' . PHP_EOL,
                    $this->port,
                );
                break;
            }
        }

        $this->dsn = $str;
        $this->appEndpoint = $this->getAppEndpoint();
        $this->otlpEndpoint = $this->getOtlpEndpoint();
    }

    private function getAppEndpoint(): string {
        if ($this->host == 'uptrace.dev') {
            return 'https://app.uptrace.dev';
        }
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }

    private function getOtlpEndpoint(): string {
        if ($this->host == 'uptrace.dev') {
            return 'https://otlp.uptrace.dev';
        }
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}

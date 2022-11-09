<?php

declare(strict_types=1);

namespace Uptrace;

use InvalidArgumentException;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Common\Environment\Variables as Env;
use OpenTelemetry\SDK\Common\Environment\KnownValues as Values;
use OpenTelemetry\SDK\Common\Log\LoggerHolder;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;

class Distro {
	private Dsn $dsn;

    public function __construct(?Config $conf = null) {
        if ($conf == null) {
            $conf = new Config();
        }

        $dsn = $conf->getDsn();
        $serviceName = $conf->getServiceName();
        $serviceVersion = $conf->getServiceVersion();
        $deploymentEnvironment = $conf->getDeploymentEnvironment();

        if ($dsn == '') {
            $msg = 'Uptrace DSN is empty (provide UPTRACE_DSN env var)';
            throw new InvalidArgumentException($msg);
        }
        $this->dsn = new Dsn($dsn);

        $resource = array_filter([
            getenv('OTEL_RESOURCE_ATTRIBUTES'),
        ]);
        if (!empty($serviceName)) {
            array_push($resource, sprintf('service.name=%s', $serviceName));
        }
        if (!empty($serviceVersion)) {
            array_push($resource, sprintf('service.version=%s', $serviceVersion));
        }
        if (!empty($deploymentEnvironment)) {
            array_push($resource, sprintf('deployment.environment=%s', $deploymentEnvironment));
        }
        putenv(sprintf('OTEL_RESOURCE_ATTRIBUTES=%s', implode(',', $resource)));
    }

    public function createTracerProvider(): TracerProvider {
        putenv(sprintf('%s=%s', Env::OTEL_PHP_TRACES_PROCESSOR, Values::VALUE_BATCH));

        $transport = (new OtlpHttpTransportFactory())
                   ->create(
                       $this->dsn->otlpEndpoint.'/v1/traces',
                       'application/x-protobuf',
                       ['uptrace-dsn' => $this->dsn->dsn],
                       TransportFactoryInterface::COMPRESSION_GZIP,
                   );
        $exporter = new SpanExporter($transport);

        $processorFactory = new SpanProcessorFactory();
        $processor = $processorFactory->fromEnvironment($exporter);

        return new TracerProvider($processor);
    }

    public function traceUrl(?Span $span = null): string {
        if ($span == null) {
            $span = Span::getCurrent();
        }
        $context = $span->getContext();
        return sprintf('%s/traces/%s', $this->dsn->appEndpoint, $context->getTraceId());
    }
}

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

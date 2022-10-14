<?php

declare(strict_types=1);

namespace Uptrace;

use InvalidArgumentException;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\Contrib\OtlpHttp\Exporter as OtlpHttpExporter;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Common\Environment\Variables as Env;
use OpenTelemetry\SDK\Common\Environment\KnownValues as Values;

class Uptrace {
	private Dsn $dsn;

    public function __construct(?string $uptraceDsn = null) {
        if ($uptraceDsn == null) {
            $uptraceDsn = getenv('UPTRACE_DSN');
            if (!$uptraceDsn) {
                $msg = "DSN is empty (pass first arg or define UPTRACE_DSN env var)";
                throw new InvalidArgumentException($msg);
            }
        }
        $this->dsn = new Dsn($uptraceDsn);
    }

    public function createTracerProvider() {
        putenv(sprintf("%s=%s", Env::OTEL_PHP_TRACES_PROCESSOR, Values::VALUE_BATCH));
        putenv(sprintf(
            "OTEL_EXPORTER_OTLP_TRACES_ENDPOINT=%s/v1/traces",
            $this->dsn->otlpEndpoint,
        ));
        putenv(sprintf("OTEL_EXPORTER_OTLP_HEADERS=uptrace-dsn=%s", $this->dsn->dsn));

        $processorFactory = new SpanProcessorFactory();
        $exporter = new OtlpHttpExporter(
            new \GuzzleHttp\Client(),
            new \GuzzleHttp\Psr7\HttpFactory(),
            new \GuzzleHttp\Psr7\HttpFactory(),
        );
        $processor = $processorFactory->fromEnvironment($exporter);

        return new TracerProvider($processor);
    }

    public function traceUrl(?Span $span = null): string {
        if ($span == null) {
            $span = Span::getCurrent();
        }
        $context = $span->getContext();
        return sprintf("%s/traces/%s", $this->dsn->appEndpoint, $context->getTraceId());
    }
}

class Dsn {
    public string $dsn = "";
    public string $appEndpoint = "";
    public string $otlpEndpoint = "";

	private string $scheme = "";
	private string $host = "";
	private int $port = 14318;

	private string $projectID = "";
	private string $token = "";

    public function __construct($str) {
        $url = parse_url($str);
        if (!$url) {
            $msg = sprintf("can't parse DSN '%s'", $str);
            throw new InvalidArgumentException($msg);
        }

        $this->scheme = $url['scheme'];
        $this->host = $url['host'];
        $this->port = $url['port'];
        switch ($this->port) { // fix common mistake
        case 4317:
            $this->port = 4318;
            break;
        case 14317:
            $this->port = 14318;
            break;
        }
        $this->projectID = $url['path'];
        $this->token = $url["user"];

        $this->dsn = $str;
        $this->appEndpoint = $this->getAppEndpoint();
        $this->otlpEndpoint = $this->getOtlpEndpoint();
    }

    private function getAppEndpoint(): string {
        if ($this->host == "uptrace.dev") {
            return "https://app.uptrace.dev";
        }
        return sprintf("%s://%s:%d", $this->scheme, $this->host, $this->port);
    }

    private function getOtlpEndpoint(): string {
        if ($this->host == "uptrace.dev") {
            return "https://otlp.uptrace.dev:4318";
        }
        return sprintf("%s://%s:%d", $this->scheme, $this->host, $this->port);
    }
}

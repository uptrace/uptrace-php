<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use OpenTelemetry\API\Logs\EventLogger;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\LogsExporter;

$dsn = getenv('UPTRACE_DSN');
if (!$dsn) {
    exit('UPTRACE_DSN environment variable is required');
}
echo 'using DSN: ', $dsn, PHP_EOL;

$resource = ResourceInfoFactory::emptyResource()->merge(
    ResourceInfo::create(Attributes::create([
        "service.name" => "test",
        "service.version" => "1.0.0",
    ])),
    ResourceInfoFactory::defaultResource()
);

$transportFactory = new OtlpHttpTransportFactory();

$transport = $transportFactory->create(
    'https://otlp.uptrace.dev/v1/logs',
    'application/json',
    ['uptrace-dsn' => $dsn],
    TransportFactoryInterface::COMPRESSION_GZIP,
);
$exporter = new LogsExporter($transport);

$processor = new BatchLogRecordProcessor(
    $exporter,
    ClockFactory::getDefault(),
);

$loggerProvider = LoggerProvider::builder()
    ->setResource($resource)
    ->addLogRecordProcessor($processor)
    ->build();

Sdk::builder()
    ->setLoggerProvider($loggerProvider)
    ->setAutoShutdown(true)
    ->buildAndRegisterGlobal();

$logger = $loggerProvider->getLogger('demo', '1.0', 'http://schema.url', [/*attributes*/]);
$eventLogger = new EventLogger($logger, 'my-domain');

$record = (new LogRecord('hello world'))
    ->setSeverityText('INFO')
    ->setAttributes([/*attributes*/]);
$eventLogger->logEvent('foo', $record);

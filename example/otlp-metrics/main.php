<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\MetricExporter;

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
$reader = new ExportingReader(
    new MetricExporter(
        $transportFactory->create(
            'https://api.uptrace.dev/v1/metrics',
            'application/json',
            ['uptrace-dsn' => $dsn],
            TransportFactoryInterface::COMPRESSION_GZIP,
        )
    ),
    ClockFactory::getDefault()
);

$meterProvider = MeterProvider::builder()
               ->setResource($resource)
               ->addReader($reader)
               ->build();

$meter = $meterProvider->getMeter('app_or_package_name');
$counter = $meter->createCounter('uptrace.demo.counter_name', '', 'counter description');

for ($i = 0; $i < 100000; $i++) {
    $counter->add(1);
    if ($i % 10 === 0) {
        $reader->collect();
    }
    usleep(100);
}

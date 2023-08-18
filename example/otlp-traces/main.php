<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;

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
    'https://otlp.uptrace.dev/v1/traces',
    'application/json',
    ['uptrace-dsn' => $dsn],
    TransportFactoryInterface::COMPRESSION_GZIP,
);
$spanExporter = new SpanExporter($transport);

$spanProcessor = new BatchSpanProcessor(
    $spanExporter,
    ClockFactory::getDefault(),
    BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE,
    BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
    BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT,
    BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
    true,
);

$tracerProvider = TracerProvider::builder()
    ->addSpanProcessor($spanProcessor)
    ->setResource($resource)
    ->setSampler(new ParentBased(new AlwaysOnSampler()))
    ->build();

Sdk::builder()
    ->setTracerProvider($tracerProvider)
    ->setPropagator(TraceContextPropagator::getInstance())
    ->setAutoShutdown(true)
    ->buildAndRegisterGlobal();

// Create a tracer. Usually, tracer is a global variable.
$tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('app_or_package_name');

// Create a root span (a trace) to measure some operation.
$main = $tracer->spanBuilder('main-operation')->startSpan();
// Future spans will be parented to the currently active span.
$mainScope = $main->activate();

$child1 = $tracer->spanBuilder('GET /posts/:id')
                 ->setSpanKind(SpanKind::KIND_SERVER)
                 ->startSpan();
$child1Scope = $child1->activate();
$child1->setAttribute('http.method"', 'GET');
$child1->setAttribute('http.route"', '/posts/:id');
$child1->setAttribute('http.url', 'http://localhost:8080/posts/123');
$child1->setAttribute('http.status_code', 200);
try {
    throw new \Exception('Some error message');
} catch (\Exception $exc) {
    $child1->setStatus('error', $exc->getMessage());
    $child1->recordException($exc);
}
$child1Scope->detach();
$child1->end();

$child2 = $tracer->spanBuilder('child2-of-main')->startSpan();
$child2Scope = $child1->activate();
$child2->setAttributes([
    'db.system' => 'mysql',
    'db.statement' => 'SELECT * FROM posts LIMIT 100',
]);
$child2Scope->detach();
$child2->end();

// End the span and detached context when the operation we are measuring is done.
$mainScope->detach();
$main->end();

$context = $main->getContext();
echo sprintf('https://app.uptrace.dev/traces/%s', $context->getTraceId()) . PHP_EOL;

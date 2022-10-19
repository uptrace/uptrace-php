<?php

declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

use OpenTelemetry\API\Trace\SpanKind;

$conf = new Uptrace\Config();
// copy your project DSN here or use UPTRACE_DSN env var
//$conf->setDsn('https://<token>@uptrace.dev/<project_id>');
$conf->setServiceName('myservice');
$conf->setServiceVersion('1.0.0');

$uptrace = new Uptrace\Distro($conf);
$tracerProvider = $uptrace->createTracerProvider();

// Create a tracer. Usually, tracer is a global variable.
$tracer = $tracerProvider->getTracer('app_or_package_name');

// Create a root span (a trace) to measure some operation.
$main = $tracer->spanBuilder('main-operation')->startSpan();
// Future spans will be parented to the currently active span.
$mainScope = $main->activate();

$child1 = $tracer->spanBuilder('child1-of-main')
                 ->setSpanKind(SpanKind::KIND_SERVER)
                 ->startSpan();
$child1Scope = $child1->activate();
$child1->setAttribute('key1', 'value1');
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
$child2->setAttributes(['key2' => 42, 'key3' => 123.456]);
$child2Scope->detach();
$child2->end();

// End the span and detached context when the operation we are measuring is done.
$mainScope->detach();
$main->end();

echo $uptrace->traceUrl($main) . PHP_EOL;

// Send buffered spans and free resources.
$tracerProvider->shutdown();

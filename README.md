# Uptrace for PHP

[![Documentation](https://img.shields.io/badge/uptrace-documentation-informational)](https://uptrace.dev/get/uptrace-php.html)
[![Chat](https://img.shields.io/badge/-telegram-red?color=white&logo=telegram&logoColor=black)](https://t.me/uptrace)

<a href="https://uptrace.dev/get/uptrace-php.html">
  <img src="https://uptrace.dev/get/devicon/php-original.svg" height="200px" />
</a>

## Introduction

uptrace-php is an OpenTelemery PHP distribution configured to export
[traces](https://uptrace.dev/opentelemetry/distributed-tracing.html) to Uptrace.

## Quickstart

First, install Composer using the
[installation instructions](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)
and add the following line to your project's `composer.json` file, as this library has not reached a
stable release status yet:

```json
 "minimum-stability": "dev"
```

Then, you can install uptrace-php:

```bash
composer require uptrace/uptrace
```

Run the [basic example](example/basic) below using the DSN from the Uptrace project settings page.

```php
<?php

declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

use Uptrace\Uptrace;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\API\Trace\SpanKind;

putenv(sprintf('OTEL_SERVICE_NAME=myservice'));

$uptrace = new Uptrace();
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
```

## Links

- [Examples](example)
- [Documentation](https://uptrace.dev/get/uptrace-php.html)
- [Instrumentations](https://uptrace.dev/opentelemetry/instrumentations/?lang=php)

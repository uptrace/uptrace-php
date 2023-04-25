# Uptrace for PHP

[![Documentation](https://img.shields.io/badge/uptrace-documentation-informational)](https://uptrace.dev/get/opentelemetry-php.html)
[![Chat](https://img.shields.io/badge/-telegram-red?color=white&logo=telegram&logoColor=black)](https://t.me/uptrace)

<a href="https://uptrace.dev/get/opentelemetry-php.html">
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

use OpenTelemetry\API\Common\Instrumentation\Globals;
use OpenTelemetry\API\Trace\SpanKind;

$uptrace = Uptrace\Distro::builder()
    // copy your project DSN here or use UPTRACE_DSN env var
    //->setDsn('https://<token>@uptrace.dev/<project_id>')
    ->setServiceName('myservice')
    ->setServiceVersion('1.0.0')
    ->buildAndRegisterGlobal();

// Create a tracer. Usually, tracer is a global variable.
$tracer = Globals::tracerProvider()->getTracer('app_or_package_name');

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

echo $uptrace->traceUrl($main) . PHP_EOL;
```

## Links

- [Examples](example)
- [Documentation](https://uptrace.dev/get/opentelemetry-php.html)
- [OpenTelemetry PHP Instrumentations](https://uptrace.dev/opentelemetry/instrumentations/?lang=php)

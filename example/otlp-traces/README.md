# OpenTelemetry PHP metrics example for Uptrace

This example demonstrates how to export OpenTelemetry Metrics to Uptrace using OTLP HTTP exporter.

Install dependencies:

```shell
composer install
```

To run this example, you need to
[create an Uptrace project](https://uptrace.dev/get/get-started.html) and pass your project DSN via
`UPTRACE_DSN` env variable:

```shell
UPTRACE_DSN=https://<token>@api.uptrace.dev/<project_id> php main.php
```

To view metrics, open [app.uptrace.dev](https://app.uptrace.dev/) and navigate to the Metrics ->
Explore tab.

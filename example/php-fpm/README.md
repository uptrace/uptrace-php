# PHP-FPM metrics example for OpenTelemetry Collector and Uptrace

To run this example, you need to
[create an Uptrace project](https://uptrace.dev/get/get-started.html) and pass your project DSN via
`UPTRACE_DSN` env variable:

```shell
UPTRACE_DSN=https://<token>@api.uptrace.dev/<project_id> docker-compose up -d
```

To view metrics, open [app.uptrace.dev](https://app.uptrace.dev/) and navigate to the Metrics ->
Explore tab.

See [OpenTelemetry PHP FPM metrics](https://uptrace.dev/get/monitor/opentelemetry-php-fpm.html) for
details.

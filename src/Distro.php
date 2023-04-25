<?php

declare(strict_types=1);

namespace Uptrace;

use OpenTelemetry\SDK\Trace\Span;

class Distro {
	private Dsn $dsn;

    public function __construct(Dsn $dsn) {
        $this->dsn = $dsn;
    }

    public function traceUrl(?Span $span = null): string {
        if ($span == null) {
            $span = Span::getCurrent();
        }
        $context = $span->getContext();
        return sprintf('%s/traces/%s', $this->dsn->appEndpoint, $context->getTraceId());
    }

    public static function builder(): DistroBuilder
    {
        return new DistroBuilder();
    }
}

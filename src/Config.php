<?php

declare(strict_types=1);

namespace Uptrace;

class Config {
    private string $dsn = '';
    private string $serviceName = '';
    private string $serviceVersion = '';

    public function __construct(?string $uptraceDsn = null) {
        $this->dsn = $uptraceDsn ?? getenv('UPTRACE_DSN');
    }

    public function setDsn(string $dsn): Config {
        $this->dsn = $dsn;
        return $this;
    }

    public function getDsn(): string {
        return $this->dsn;
    }

    public function setServiceName(string $serviceName): Config {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function getServiceName(): string {
        return $this->serviceName;
    }

    public function setServiceVersion(string $serviceVersion): Config {
        $this->serviceVersion = $serviceVersion;
        return $this;
    }

    public function getServiceVersion(): string {
        return $this->serviceVersion;
    }
}

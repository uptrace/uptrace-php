<?php

declare(strict_types=1);

namespace Uptrace;

class Config {
    private string $dsn = '';
    private string $serviceName = '';
    private string $serviceVersion = '';
    private string $deploymentEnvironment = '';

    public function __construct(?string $uptraceDsn = '') {
        $this->dsn = $uptraceDsn;
        if (empty($this->dsn)) {
            $env = getenv('UPTRACE_DSN');
            if (!empty($env)) {
                $this->dsn = $env;
            }
        }
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

    public function setDeploymentEnvironment(string $deploymentEnvironment): Config {
        $this->deploymentEnvironment = $deploymentEnvironment;
        return $this;
    }

    public function getDeploymentEnvironment(): string {
        return $this->deploymentEnvironment;
    }
}

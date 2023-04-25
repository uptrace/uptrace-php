<?php

declare(strict_types=1);

namespace Uptrace;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Log\LoggerHolder;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\Sampler;
use OpenTelemetry\SDK\Trace\SpanLimitsBuilder;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Aws\Xray\IdGenerator;

class DistroBuilder {
    private OtlpHttpTransportFactory $transportFactory;

    private string $dsn = '';
    private ?SamplerInterface $sampler = null;

    private array $resourceAttrs = [];
    private string $serviceName = '';
    private string $serviceVersion = '';
    private string $deploymentEnvironment = '';

    public function __construct() {
        $this->transportFactory = new OtlpHttpTransportFactory();
        $this->sampler = new Sampler\ParentBased(new Sampler\AlwaysOnSampler());

        $env = getenv('UPTRACE_DSN');
        if (!empty($env)) {
            $this->dsn = $env;
        }
    }

    public function setDsn(string $dsn): DistroBuilder {
        $this->dsn = $dsn;
        return $this;
    }

    public function setServiceName(string $serviceName): DistroBuilder {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function setServiceVersion(string $serviceVersion): DistroBuilder {
        $this->serviceVersion = $serviceVersion;
        return $this;
    }

    public function setDeploymentEnvironment(string $deploymentEnvironment): self
    {
        $this->deploymentEnvironment = $deploymentEnvironment;
        return $this;
    }

    public function setSampler(SamplerInterface $sampler): self
    {
        $this->sampler = $sampler;
        return $this;
    }

    public function buildAndRegisterGlobal()
    {
        if (empty($this->dsn)) {
            $msg = 'Uptrace DSN is empty (provide UPTRACE_DSN env var)';
            throw new \InvalidArgumentException($msg);
        }

        $dsn = new Dsn($this->dsn);
        $resource = $this->createResource();

        $resource = $this->createResource();
        $meterProvider = $this->createMeterProvider($dsn, $resource);
        $tracerProvider = $this->createTracerProvider($dsn, $resource, $meterProvider);

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        return new Distro($dsn);
    }

    private function createResource() {
        return ResourceInfoFactory::merge(
            $this->createResourceFromAttrs(),
            ResourceInfoFactory::defaultResource()
        );
    }

    private function createResourceFromAttrs(): ResourceInfo {
        $attrs = $this->resourceAttrs;
        if (!empty($this->serviceName)) {
            $attrs[ResourceAttributes::SERVICE_NAME] = $this->serviceName;
        }
        if (!empty($this->serviceVersion)) {
            $attrs[ResourceAttributes::SERVICE_VERSION] = $this->serviceVersion;
        }
        if (!empty($this->deploymentEnvironment)) {
            $attrs[esourceAttributes::DEPLOYMENT_ENVIRONMENT] = $this->deploymentEnvironment;
        }
        return ResourceInfo::create(Attributes::create($attrs));
    }

    private function createMeterProvider(Dsn $dsn, ResourceInfo $resource): MeterProvider {
        $reader = new ExportingReader(
            new MetricExporter(
                $this->transportFactory->create(
                    $dsn->otlpEndpoint.'/v1/metrics',
                    'application/json',
                    ['uptrace-dsn' => $dsn->dsn],
                    TransportFactoryInterface::COMPRESSION_GZIP,
                )
            ),
            ClockFactory::getDefault()
        );

        return MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();
    }

    private function createTracerProvider(
        Dsn $dsn, ResourceInfo $resource, MeterProvider $meterProvider
    ): TracerProvider {
        $transport = $this->transportFactory->create(
            $dsn->otlpEndpoint.'/v1/traces',
            'application/json',
            ['uptrace-dsn' => $dsn->dsn],
            TransportFactoryInterface::COMPRESSION_GZIP,
        );
        $exporter = new SpanExporter($transport);

        $processor = new BatchSpanProcessor(
            $exporter,
            ClockFactory::getDefault(),
            BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE,
            BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
            BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT,
            BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            true,
            $meterProvider
        );

        $spanLimits = (new SpanLimitsBuilder())->build();
        $idGenerator = new IdGenerator();

        return new TracerProvider($processor, $this->sampler, $resource, $spanLimits, $idGenerator);
    }
}
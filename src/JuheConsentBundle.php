<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class JuheConsentBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The anonymous consent log takes at most this many entries per visitor
        $builder->prependExtensionConfig('framework', ['rate_limiter' => ['juhe_consent_log' => [
            'policy' => 'sliding_window',
            'limit' => 20,
            'interval' => '10 minutes',
        ]]]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}

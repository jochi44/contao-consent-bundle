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
        // The consent log takes at most this many entries per IP. Many visitors can
        // share one IP (office or carrier NAT), so the cap only stops floods; a site
        // may set framework.rate_limiter.juhe_consent_log itself.
        $builder->prependExtensionConfig('framework', ['rate_limiter' => ['juhe_consent_log' => [
            'policy' => 'sliding_window',
            'limit' => 120,
            'interval' => '10 minutes',
        ]]]);

        // The same cap for the cookie cleanup on withdrawal (framework.rate_limiter.juhe_consent_cookies)
        $builder->prependExtensionConfig('framework', ['rate_limiter' => ['juhe_consent_cookies' => [
            'policy' => 'sliding_window',
            'limit' => 120,
            'interval' => '10 minutes',
        ]]]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}

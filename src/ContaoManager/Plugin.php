<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use JuheItSolutions\JuheConsentBundle\JuheConsentBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [BundleConfig::create(JuheConsentBundle::class)->setLoadAfter([ContaoCoreBundle::class])];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection|null
    {
        $path = __DIR__.'/../../config/routes.yaml';

        $loader = $resolver->resolve($path);

        return false === $loader ? null : $loader->load($path);
    }
}

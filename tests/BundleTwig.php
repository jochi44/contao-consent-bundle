<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * A plain Twig environment with the bundle's templates under @Contao, for
 * templates that use no Contao extension (no attrs(), trans or asset()).
 */
final class BundleTwig
{
    public static function create(string ...$extraPaths): Environment
    {
        $loader = new FilesystemLoader();

        foreach ($extraPaths as $path) {
            $loader->addPath($path, 'Contao');
        }

        $loader->addPath(\dirname(__DIR__).'/contao/templates', 'Contao');

        return new Environment($loader, ['strict_variables' => true, 'autoescape' => 'name']);
    }
}

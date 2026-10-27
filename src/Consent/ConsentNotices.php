<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Twig\Loader\LoaderInterface;

/**
 * The notice that stands in for a blocked iframe: juhe_consent/placeholder or,
 * per service, one of its variants (juhe_consent/placeholder/*).
 */
final class ConsentNotices
{
    public const DEFAULT = 'juhe_consent/placeholder';

    private const NAME = '#^juhe_consent/placeholder(?:/[a-z0-9][a-z0-9_-]*)+$#i';

    public function __construct(private readonly FinderFactory $finderFactory)
    {
    }

    /**
     * The default template (empty value) and its variants.
     *
     * @return array<string, string>
     */
    #[AsCallback(table: 'tl_juhe_consent_service', target: 'fields.noticeTemplate.options')]
    public function options(): array
    {
        return $this->finderFactory
            ->create()
            ->identifier(self::DEFAULT)
            ->extension('html.twig')
            ->withVariants()
            ->excludePartials()
            ->asTemplateOptions()
        ;
    }

    /**
     * A variant name; the default template is stored as an empty value.
     */
    public static function isName(string $identifier): bool
    {
        return 1 === preg_match(self::NAME, $identifier);
    }

    /**
     * The logical name to render for a service: its variant if that exists,
     * else the default template.
     */
    public static function template(string $identifier, LoaderInterface $loader): string
    {
        $name = '@Contao/'.$identifier.'.html.twig';

        return self::isName($identifier) && $loader->exists($name) ? $name : '@Contao/'.self::DEFAULT.'.html.twig';
    }
}

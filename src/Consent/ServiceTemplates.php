<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Twig\Environment;

/**
 * Services of the type "Template": the code after consent comes from a Twig
 * template under juhe_consent/service/ (the shipped presets or the site's
 * own), not from the code field. The output goes into the same inert
 * <template> as the code, so nothing of it runs before consent.
 */
final class ServiceTemplates
{
    public const PREFIX = 'juhe_consent/service';

    /**
     * A template name the back end can list: below juhe_consent/service/, no
     * partials (a name starting with "_") and no "." or ".." segments.
     */
    private const NAME = '#^juhe_consent/service(?:/[a-z0-9][a-z0-9_-]*)+$#i';

    public function __construct(
        private readonly FinderFactory $finderFactory,
        private readonly Environment $twig,
    ) {
    }

    /**
     * The templates the back end offers: every juhe_consent/service/* Twig template.
     *
     * @return array<string, string>
     */
    #[AsCallback(table: 'tl_juhe_consent_service', target: 'fields.serviceTemplate.options')]
    public function options(): array
    {
        return $this->finderFactory
            ->create()
            ->identifier(self::PREFIX)
            ->extension('html.twig')
            ->withVariants(true)
            ->excludePartials()
            ->asTemplateOptions()
        ;
    }

    public static function isName(string $identifier): bool
    {
        return 1 === preg_match(self::NAME, $identifier);
    }

    /**
     * The template's output, or an empty string for a name that is not a
     * listed template (a stored value from elsewhere, a deleted template).
     *
     * @param array<string, mixed> $context
     */
    public function render(string $identifier, array $context): string
    {
        $name = '@Contao/'.$identifier.'.html.twig';

        if (!self::isName($identifier) || !$this->twig->getLoader()->exists($name)) {
            return '';
        }

        return trim($this->twig->render($name, $context));
    }
}

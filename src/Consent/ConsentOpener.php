<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Contao\PageModel;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the settings opener needs (juhe_consent/opener.html.twig): its label
 * and the privacy policy page of the page's banner, the target without
 * JavaScript.
 */
final class ConsentOpener
{
    public function __construct(
        private readonly ConsentConfig $config,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{label: string, url: string|null}
     */
    public function context(PageModel|null $page, string|null $label = null): array
    {
        $banner = $page ? $this->config->bannerForPage($page) : null;

        return [
            'label' => '' !== trim((string) $label) ? (string) $label : $this->translator->trans('settings_link', [], 'juhe_consent'),
            'url' => $banner['privacy_url'] ?? null,
        ];
    }
}

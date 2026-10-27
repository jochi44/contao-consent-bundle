<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\InsertTag;

use Contao\CoreBundle\DependencyInjection\Attribute\AsInsertTag;
use Contao\CoreBundle\InsertTag\Exception\InvalidInsertTagException;
use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\OutputType;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\PageModel;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentOpener;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * {{juhe_consent::settings}}: the settings opener (juhe_consent/opener.html.twig)
 * in a text, e.g. "You can change your choice in the {{juhe_consent::settings}}."
 */
final class ConsentSettingsInsertTag
{
    public function __construct(
        private readonly ConsentOpener $opener,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsInsertTag('juhe_consent')]
    public function __invoke(ResolvedInsertTag $insertTag): InsertTagResult
    {
        if ('settings' !== $insertTag->getParameters()->get(0)) {
            throw new InvalidInsertTagException('Unknown juhe_consent insert tag, use {{juhe_consent::settings}}.');
        }

        $page = $this->requestStack->getCurrentRequest()?->attributes->get('pageModel');

        return new InsertTagResult(
            trim($this->twig->render('@Contao/juhe_consent/opener.html.twig', $this->opener->context($page instanceof PageModel ? $page : null))),
            OutputType::html,
        );
    }
}

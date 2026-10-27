<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The global operation "Create essential group" changes data, so it is a POST
 * form with the request token instead of a link (template
 * juhe_consent/backend/essential_operation.html.twig).
 */
#[AsCallback(table: 'tl_juhe_consent_group', target: 'list.global_operations.essential.button')]
final class EssentialOperationListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContaoCsrfTokenManager $tokenManager,
    ) {
    }

    public function __invoke(DataContainerOperation $operation): void
    {
        $bannerId = (int) $operation->getDataContainer()->id;

        if ($bannerId < 1) {
            $operation->hide();

            return;
        }

        $operation->setHtml(trim($this->twig->render('@Contao/juhe_consent/backend/essential_operation.html.twig', [
            'action' => $this->urlGenerator->generate('juhe_consent_essential', ['id' => $bannerId]),
            'request_token' => $this->tokenManager->getDefaultTokenValue(),
            'label' => (string) $operation['label'],
            'title' => (string) $operation['title'],
        ])));
    }
}

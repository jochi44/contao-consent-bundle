<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A button that opens the consent settings again, e.g. on the privacy policy
 * page. Links to "#cookie-einstellungen" do the same.
 */
#[AsContentElement('juhe_consent_settings', category: 'links')]
final class ConsentSettingsController extends AbstractContentElementController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $template->set('label', $model->linkTitle ?: $this->translator->trans('settings_link', [], 'juhe_consent'));

        return $template->getResponse();
    }
}

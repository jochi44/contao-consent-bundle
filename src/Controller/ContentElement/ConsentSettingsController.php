<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentOpener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A link or button that opens the consent settings again, e.g. on the privacy
 * policy page. Links to "#juhe-consent" or "#cookie-einstellungen" do the same.
 */
#[AsContentElement('juhe_consent_settings', category: 'links')]
final class ConsentSettingsController extends AbstractContentElementController
{
    public function __construct(private readonly ConsentOpener $opener)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        foreach ($this->opener->context($this->getPageModel(), $model->linkTitle) as $name => $value) {
            $template->set($name, $value);
        }

        return $template->getResponse();
    }
}

<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentOpener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The front end module "Cookie-Einstellungen": the same opener as the content
 * element, for a layout (e.g. the footer).
 */
#[AsFrontendModule('juhe_consent_settings', category: 'miscellaneous')]
final class ConsentSettingsModuleController extends AbstractFrontendModuleController
{
    public function __construct(private readonly ConsentOpener $opener)
    {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        foreach ($this->opener->context($this->getPageModel(), $model->customLabel) as $name => $value) {
            $template->set($name, $value);
        }

        return $template->getResponse();
    }
}

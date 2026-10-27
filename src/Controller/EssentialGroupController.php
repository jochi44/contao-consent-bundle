<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\DcaLoader;
use Contao\Message;
use JuheItSolutions\JuheConsentBundle\Consent\EssentialStarter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Create essential group" in a banner's group list (a POST form, see
 * EssentialOperationListener): the essential group and the service for the
 * stored choice, each only if missing. Back to the group list afterwards.
 */
#[Route('/contao/juhe-consent/essential/{id}', name: 'juhe_consent_essential', requirements: ['id' => '\d+'], methods: ['POST'], defaults: ['_scope' => 'backend'])]
final class EssentialGroupController
{
    public const MODULE = 'juhe_consent';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ContaoCsrfTokenManager $tokenManager,
        private readonly EssentialStarter $starter,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%contao.csrf_token_name%')]
        private readonly string $tokenName,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        if (!$this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, (string) $request->request->get('REQUEST_TOKEN')))) {
            throw new AccessDeniedException('Invalid request token.');
        }

        $this->framework->initialize();

        foreach (['tl_juhe_consent_group', 'tl_juhe_consent_service'] as $table) {
            $this->framework->createInstance(DcaLoader::class, [$table])->load();
        }

        if (
            !$this->authorizationChecker->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, self::MODULE)
            || !$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_juhe_consent_group', new CreateAction('tl_juhe_consent_group', ['pid' => $id]))
            || !$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_juhe_consent_service', new CreateAction('tl_juhe_consent_service'))
        ) {
            throw new AccessDeniedException('No permission to create consent groups and services.');
        }

        $t = fn (string $key, array $parameters = []): string => $this->translator->trans('tl_juhe_consent_group.'.$key, $parameters, 'contao_default');

        $created = $this->starter->run($id, [
            'group_title' => $t('essentialTitle'),
            'group_description' => $t('essentialDescription'),
            'service_title' => $t('choiceTitle'),
            'service_provider' => $t('choiceProvider'),
            'service_description' => $t('choiceDescription'),
            'duration' => $t('choiceDuration'),
        ]);

        if (null === $created) {
            throw new NotFoundHttpException('No such banner.');
        }

        $message = $this->framework->getAdapter(Message::class);

        if (null !== $created['group']) {
            $message->addConfirmation($t('essentialCreatedGroup', [$t('essentialTitle')]));
        }

        if (null !== $created['service']) {
            $message->addConfirmation($t('essentialCreatedService', [$t('choiceTitle')]));
        }

        if (null === $created['group'] && null === $created['service']) {
            $message->addInfo($t('essentialExists'));
        }

        return new RedirectResponse($this->urlGenerator->generate('contao_backend', ['do' => self::MODULE, 'table' => 'tl_juhe_consent_group', 'id' => $id]), Response::HTTP_SEE_OTHER);
    }
}

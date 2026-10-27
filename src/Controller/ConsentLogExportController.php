<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentLogExport;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * "Export (CSV)" in the consent log: the entries of the current filter and
 * search as a CSV download, for back end users with access to the log module.
 */
#[Route('/contao/juhe-consent/log-export', name: 'juhe_consent_log_export', methods: ['GET'], defaults: ['_scope' => 'backend'])]
final class ConsentLogExportController
{
    public const MODULE = 'juhe_consent_log';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ConsentLogExport $export,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, self::MODULE)) {
            throw new AccessDeniedException('No access to the consent log.');
        }

        $bag = $request->getSession()->getBag('contao_backend');
        [$where, $values] = $this->export->conditions($bag instanceof AttributeBagInterface ? $bag->all() : []);

        $response = new StreamedResponse(function () use ($where, $values): void {
            $stream = fopen('php://output', 'w');
            $this->export->write($stream, $where, $values, static fn () => flush());
            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'consent-log-'.date('Y-m-d').'.csv'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\PageModel;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use JuheItSolutions\JuheConsentBundle\Consent\EmbedKind;
use JuheItSolutions\JuheConsentBundle\Consent\HtmlBlocker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Adds the banner to every frontend page of a site with a consent banner and
 * holds back blocked iframes and scripts. Works on the finished HTML, so it
 * does not depend on the page layout or the theme.
 *
 * Contao has already written the CSP header at this point, so the script
 * nonce is added to the header here (or the page's nonce is reused).
 *
 * The banner is the same for every visitor (the choice lives in browser storage
 * and is applied by consent.js), so cached pages stay cacheable. The one
 * exception, the page for a logged-in user, is made private (see restricted()).
 */
#[AsEventListener(priority: 10)]
final class InjectConsentListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ConsentConfig $config,
        private readonly HtmlBlocker $blocker,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly CacheTagManager $cacheTagManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenChecker $tokenChecker,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            !$this->scopeMatcher->isFrontendMainRequest($event)
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || !str_starts_with((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')
            || $response->isRedirection()
        ) {
            return;
        }

        $page = $request->attributes->get('pageModel');
        $html = (string) $response->getContent();

        if (!$page instanceof PageModel || !preg_match('/<body\\b/i', $html)) {
            return;
        }

        $banner = $this->config->bannerForPage($page);

        if (null === $banner) {
            return;
        }

        $banner['restricted'] = $this->restricted($request, $response, $banner);

        // Edits to the banner, its groups or services clear the cached pages
        $this->cacheTagManager->tagWith(['contao.db.tl_juhe_consent_banner', 'contao.db.tl_juhe_consent_group', 'contao.db.tl_juhe_consent_service']);

        $locale = $request->getLocale();
        $trans = fn (string $key): string => $this->translator->trans($key, [], 'juhe_consent', $locale);

        $placeholderText = $banner['placeholder'] ?: $trans('placeholder');
        $count = 0;

        $html = $this->blocker->block($html, $banner, function (array $service, string $original) use (&$count, $placeholderText, $trans): string {
            return $this->twig->render('@Contao/juhe_consent/placeholder.html.twig', [
                'id' => 'juhe-consent-placeholder-'.++$count,
                'group' => $service['group'],
                'kind' => EmbedKind::fromIframe($original),
                'service' => ['key' => $service['key'], 'title' => $service['title'], 'provider' => $service['provider'], 'privacy_url' => $service['privacy_url']],
                'text' => str_replace('##service##', $service['title'], $placeholderText),
                'load_once' => $trans('load_once'),
                'load_always' => $trans('load_always'),
                'original' => $original,
            ]);
        });

        $context = [
            'banner' => $banner,
            'nonce' => $this->scriptNonce($response),
            'script_config' => json_encode($this->scriptConfig($banner), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];

        $html = $this->insertBefore($html, '</head>', $this->twig->render('@Contao/juhe_consent/head.html.twig', $context));

        // At the end of the page, behind the theme's skip link; juhe-consent.js moves the
        // first-visit card to the top while it is shown, so keyboard users meet it first
        $html = $this->insertBefore($html, '</body>', $this->twig->render('@Contao/juhe_consent/banner.html.twig', $context));

        $response->setContent($html);
        $response->headers->remove('Content-Length');
    }

    /**
     * What consent.js needs; the texts stay in the HTML.
     *
     * @param array<string, mixed> $banner
     *
     * @return array<string, mixed>
     */
    private function scriptConfig(array $banner): array
    {
        return [
            'banner' => $banner['id'],
            'version' => $banner['version'],
            'lifetime' => $banner['lifetime'] ?? ConsentConfig::DEFAULT_LIFETIME,
            'ask' => $banner['ask'],
            'quiet' => $banner['quiet'] ?? false,
            'privacySignals' => $banner['privacy_signals'] ?? false,
            'restricted' => $banner['restricted'] ?? false,
            'layout' => $banner['layout'],
            'blocking' => $banner['blocking'],
            'storage' => 'juhe_consent_'.$banner['id'],
            // Without logging the script posts nothing
            'logUrl' => ($banner['log'] ?? true) ? $this->urlGenerator->generate('juhe_consent_log') : null,
            'logPage' => $banner['log_page'] ?? 'path',
            // Expires the HttpOnly cookies of withdrawn services
            'cookieUrl' => $this->urlGenerator->generate('juhe_consent_cookies'),
            'groups' => array_map(static fn (array $group): array => [
                'alias' => $group['alias'],
                'essential' => $group['essential'],
                'services' => array_map(static fn (array $service): array => [
                    'key' => $service['key'],
                    'cookies' => array_column($service['cookies'], 'name'),
                ], $group['services']),
            ], $banner['groups']),
        ];
    }

    /**
     * "No optional services for logged-in users" covers front end members and
     * back end users in the front end preview. Their page differs from the
     * public one, so it is made private before anything is added: it never
     * enters a shared cache. (Contao makes it private later anyway, because the
     * visitor has a session.) An anonymous page is left as it is. A page that a
     * proxy serves from its cache never reaches this listener, so with "always
     * load from cache" a member can still get the public page.
     *
     * @param array<string, mixed> $banner
     */
    private function restricted(Request $request, Response $response, array $banner): bool
    {
        if (!($banner['restrict_users'] ?? false)) {
            return false;
        }

        if (!$this->tokenChecker->hasFrontendUser() && !($request->attributes->get('_preview', false) && $this->tokenChecker->hasBackendUser())) {
            return false;
        }

        $response->setPrivate();

        return true;
    }

    /**
     * The nonce for the added scripts: the one in the CSP header, or a new one
     * added to it. A policy that allows inline scripts without a nonce stays
     * as it is, because a nonce would switch 'unsafe-inline' off.
     */
    private function scriptNonce(Response $response): string|null
    {
        $nonce = null;

        foreach (['Content-Security-Policy', 'Content-Security-Policy-Report-Only'] as $header) {
            // Intermediaries may combine several policy headers into a comma-separated list.
            $policies = array_merge(...array_map(static fn (string $value): array => explode(',', $value), $response->headers->all($header)));

            foreach ($policies as &$policy) {
                $directives = [];

                foreach (array_filter(array_map('trim', explode(';', $policy))) as $directive) {
                    $parts = preg_split('/\\s+/', $directive);
                    $directives[strtolower($parts[0])] ??= $parts;
                }

                $name = array_find(['script-src-elem', 'script-src', 'default-src'], static fn (string $name): bool => isset($directives[$name]));

                if (null === $name) {
                    continue;
                }

                $existing = [];

                foreach ($directives[$name] as $source) {
                    if (preg_match("/^'nonce-([A-Za-z0-9+\\/=_-]+)'$/", $source, $match)) {
                        $existing[] = $match[1];
                    }
                }

                $nonce ??= $existing[0] ?? null;

                if (null !== $nonce && \in_array($nonce, $existing, true)) {
                    continue;
                }

                $hasHashes = (bool) preg_grep("/^'sha(256|384|512)-/", $directives[$name]);

                if (!$existing && \in_array("'unsafe-inline'", $directives[$name], true) && !$hasHashes) {
                    continue;
                }

                $nonce ??= base64_encode(random_bytes(18));

                // Do not relax default-src for styles or other resource types.
                if ('default-src' === $name) {
                    $directives['script-src'] = $directives[$name];
                    $directives['script-src'][0] = 'script-src';
                    $name = 'script-src';
                }

                $directives[$name][] = "'nonce-{$nonce}'";
                $policy = implode('; ', array_map(static fn (array $parts): string => implode(' ', $parts), $directives));
            }
            unset($policy);

            if ($policies) {
                $response->headers->set($header, $policies);
            }
        }

        return $nonce;
    }

    private function insertBefore(string $html, string $tag, string $content): string
    {
        $position = strripos($html, $tag);

        return false === $position ? $html : substr($html, 0, $position).$content.substr($html, $position);
    }
}

<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use JuheItSolutions\JuheConsentBundle\Consent\HtmlBlocker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
 * The banner is the same for every visitor (the choice lives in a cookie
 * and is applied by consent.js), so cached pages stay cacheable.
 */
#[AsEventListener(priority: 10)]
final class InjectConsentListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Connection $connection,
        private readonly ConsentConfig $config,
        private readonly HtmlBlocker $blocker,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly CacheTagManager $cacheTagManager,
        private readonly UrlGeneratorInterface $urlGenerator,
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

        $bannerId = $this->bannerId($page);
        $banner = $bannerId ? $this->config->banner($bannerId) : null;

        if (null === $banner) {
            return;
        }

        // Edits to the banner, its groups or services clear the cached pages
        $this->cacheTagManager->tagWith(['contao.db.tl_juhe_consent_banner', 'contao.db.tl_juhe_consent_group', 'contao.db.tl_juhe_consent_service']);

        $locale = $request->getLocale();
        $trans = fn (string $key): string => $this->translator->trans($key, [], 'juhe_consent', $locale);

        $html = $this->blocker->block($html, $banner, $banner['placeholder'] ?: $trans('placeholder'), $trans('load_once'), $trans('load_always'));

        $context = [
            'banner' => $banner,
            'nonce' => $this->scriptNonce($response),
            'script_config' => json_encode($this->scriptConfig($banner), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];

        $html = $this->insertBefore($html, '</head>', $this->twig->render('@Contao/juhe_consent/head.html.twig', $context));

        // Right after <body>, so keyboard and screen reader users meet it first
        $html = preg_replace('/<body\b[^>]*>/i', '$0'.addcslashes($this->twig->render('@Contao/juhe_consent/banner.html.twig', $context), '\\$'), $html, 1) ?? $html;

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
            'ask' => $banner['ask'],
            'cookie' => 'juhe_consent_'.$banner['id'],
            'logUrl' => $this->urlGenerator->generate('juhe_consent_log'),
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

    private function bannerId(PageModel $page): int
    {
        return (int) $this->connection->fetchOne('SELECT juheConsentBanner FROM tl_page WHERE id = ?', [(int) $page->loadDetails()->rootId]);
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
            $policy = $response->headers->get($header);

            if (null === $policy) {
                continue;
            }

            $directives = [];

            foreach (array_filter(array_map('trim', explode(';', $policy))) as $directive) {
                $parts = preg_split('/\s+/', $directive);
                $directives[strtolower($parts[0])] = $parts;
            }

            // The browser uses the most specific directive for <script> elements
            $name = array_find(['script-src-elem', 'script-src', 'default-src'], static fn (string $name): bool => isset($directives[$name]));

            if (null === $name) {
                continue;
            }

            foreach ($directives[$name] as $source) {
                if (preg_match("/^'nonce-([A-Za-z0-9+\/=_-]+)'$/", $source, $match)) {
                    $nonce ??= $match[1];

                    continue 2;
                }
            }

            $hasHashes = (bool) preg_grep("/^'sha(256|384|512)-/", $directives[$name]);

            if (\in_array("'unsafe-inline'", $directives[$name], true) && !$hasHashes) {
                continue;
            }

            $nonce ??= base64_encode(random_bytes(18));
            $directives[$name][] = "'nonce-{$nonce}'";

            $response->headers->set($header, implode('; ', array_map(static fn (array $parts): string => implode(' ', $parts), $directives)));
        }

        return $nonce;
    }

    private function insertBefore(string $html, string $tag, string $content): string
    {
        $position = strripos($html, $tag);

        return false === $position ? $html : substr($html, 0, $position).$content.substr($html, $position);
    }
}

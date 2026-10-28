<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Contao\CoreBundle\Cache\CacheTagManager;
use Doctrine\DBAL\Connection;

/**
 * "Create essential group" in a banner's group list: adds the essential group
 * and a service that describes the stored choice (the localStorage entry
 * juhe_consent_<banner id>), each only if the banner has none yet. Running
 * it again adds nothing. It assumes no optional provider. The records are
 * written directly, so it clears the cached pages that show the banner itself,
 * as the back end does on saving.
 */
final class EssentialStarter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CacheTagManager $cacheTagManager,
    ) {
    }

    /**
     * @param array{group_title: string, group_description: string, service_title: string, service_provider: string, service_description: string, duration: string} $texts in the editor's language; duration has a %d for the days
     *
     * @return array{group: int|null, service: int|null}|null the new IDs (null = there was one), or null if the banner does not exist
     */
    public function run(int $bannerId, array $texts): array|null
    {
        $banner = $this->connection->fetchAssociative('SELECT id, lifetime FROM tl_juhe_consent_banner WHERE id = ?', [$bannerId]);

        if (!$banner) {
            return null;
        }

        $created = $this->connection->transactional(function () use ($bannerId, $banner, $texts): array {
            $created = ['group' => null, 'service' => null];
            $groups = $this->connection->fetchAllAssociative('SELECT id, alias, essential, sorting FROM tl_juhe_consent_group WHERE pid = ? ORDER BY sorting FOR UPDATE', [$bannerId]);
            $essential = array_values(array_filter($groups, static fn (array $group): bool => (bool) $group['essential']));
            $groupId = isset($essential[0]) ? (int) $essential[0]['id'] : null;

            if (null === $groupId) {
                $first = $groups ? min(array_map('intval', array_column($groups, 'sorting'))) : 256;

                $this->connection->insert('tl_juhe_consent_group', [
                    'pid' => $bannerId,
                    'sorting' => intdiv($first, 2),
                    'tstamp' => time(),
                    'title' => mb_substr($texts['group_title'], 0, 64),
                    'alias' => self::alias('essential', array_column($groups, 'alias')),
                    'essential' => 1,
                    'description' => $texts['group_description'],
                    'published' => 1,
                ]);

                $groupId = $created['group'] = (int) $this->connection->lastInsertId();
            }

            if (!$this->describesTheChoice($bannerId, array_merge(array_map('intval', array_column($groups, 'id')), [$groupId]))) {
                $next = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(sorting), 0) FROM tl_juhe_consent_service WHERE pid = ?', [$groupId]);

                $this->connection->insert('tl_juhe_consent_service', [
                    'pid' => $groupId,
                    'sorting' => $next + 128,
                    'tstamp' => time(),
                    'title' => mb_substr($texts['service_title'], 0, 128),
                    'provider' => mb_substr($texts['service_provider'], 0, 255),
                    'description' => $texts['service_description'],
                    'cookies' => self::storageName($bannerId).': '.\sprintf($texts['duration'], ConsentConfig::lifetime($banner['lifetime'] ?? null)),
                    'published' => 1,
                ]);

                $created['service'] = (int) $this->connection->lastInsertId();
            }

            return $created;
        });

        // The pages carry these tags (InjectConsentListener)
        $tags = array_keys(array_filter(['contao.db.tl_juhe_consent_group' => $created['group'], 'contao.db.tl_juhe_consent_service' => $created['service']], static fn (int|null $id): bool => null !== $id));

        if ($tags) {
            $this->cacheTagManager->invalidateTags($tags);
        }

        return $created;
    }

    public static function storageName(int $bannerId): string
    {
        return 'juhe_consent_'.$bannerId;
    }

    /**
     * Whether a cookie line names the stored choice: the exact name or a prefix
     * pattern ("juhe_consent_*") that covers it.
     */
    public static function covers(string $cookies, int $bannerId): bool
    {
        $name = self::storageName($bannerId);

        foreach (ConsentConfig::cookies($cookies) as $cookie) {
            $pattern = $cookie['name'];

            if ($pattern === $name || (\strlen($pattern) > 1 && str_ends_with($pattern, '*') && str_starts_with($name, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * A free alias: "essential", else "essential-2", "essential-3" ...
     *
     * @param list<mixed> $taken
     */
    public static function alias(string $alias, array $taken): string
    {
        $taken = array_map('strval', $taken);
        $candidate = $alias;

        for ($i = 2; \in_array($candidate, $taken, true); ++$i) {
            $candidate = $alias.'-'.$i;
        }

        return $candidate;
    }

    /**
     * @param list<int> $groupIds
     */
    private function describesTheChoice(int $bannerId, array $groupIds): bool
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT cookies FROM tl_juhe_consent_service WHERE pid IN (?)',
            [array_values(array_unique($groupIds))],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );

        foreach ($rows as $cookies) {
            if (self::covers((string) $cookies, $bannerId)) {
                return true;
            }
        }

        return false;
    }
}

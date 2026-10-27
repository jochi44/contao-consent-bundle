<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ConsentConfigTest extends TestCase
{
    #[TestWith([6, 6])]
    #[TestWith(['12', 12])]
    #[TestWith([24, 24])]
    #[TestWith([36, 36])]
    #[TestWith([0, 36])]
    #[TestWith([7, 36])]
    #[TestWith([null, 36])]
    #[TestWith(['abc', 36])]
    public function testLogRetentionAcceptsOnlyTheOfferedMonths(mixed $value, int $expected): void
    {
        $this->assertSame($expected, ConsentConfig::logRetention($value));
    }

    #[TestWith(['path', 'path'])]
    #[TestWith(['none', 'none'])]
    #[TestWith(['', 'path'])]
    #[TestWith([null, 'path'])]
    #[TestWith(['full', 'path'])]
    public function testLogPageFallsBackToThePath(mixed $value, string $expected): void
    {
        $this->assertSame($expected, ConsentConfig::logPage($value));
    }

    #[TestWith(['bar', 'bar'])]
    #[TestWith(['card', 'card'])]
    #[TestWith(['', 'card'])]
    #[TestWith([null, 'card'])]
    #[TestWith(['BAR', 'card'])]
    #[TestWith(['<script>', 'card'])]
    public function testLayoutFallsBackToTheCard(mixed $value, string $expected): void
    {
        $this->assertSame($expected, ConsentConfig::layout($value));
    }

    #[TestWith([8, false])]
    #[TestWith([9, false])]
    #[TestWith([10, true])]
    #[TestWith([0, true])]
    public function testTheLegalPagesAreNeverBlocked(int $pageId, bool $blocking): void
    {
        $banner = ['blocking' => true, 'legal_pages' => [8, 9]];

        $this->assertSame($blocking, ConsentConfig::forPage($banner, $pageId)['blocking']);
    }

    public function testANonBlockingBannerStaysNonBlocking(): void
    {
        $this->assertFalse(ConsentConfig::forPage(['blocking' => false, 'legal_pages' => [8]], 10)['blocking']);
        $this->assertFalse(ConsentConfig::forPage(['blocking' => false], 8)['blocking']);
    }

    public function testAnExcludedPageDoesNotOpenTheBannerAndNeverBlocks(): void
    {
        $banner = ['blocking' => true, 'quiet' => false, 'legal_pages' => [8], 'quiet_pages' => [12, 14]];

        $excluded = ConsentConfig::forPage($banner, 14);
        $this->assertTrue($excluded['quiet']);
        $this->assertFalse($excluded['blocking']);

        $ordinary = ConsentConfig::forPage($banner, 10);
        $this->assertFalse($ordinary['quiet']);
        $this->assertTrue($ordinary['blocking']);

        // The legal pages still ask by themselves, only without blocking
        $this->assertFalse(ConsentConfig::forPage($banner, 8)['quiet']);
    }

    #[TestWith([null, []])]
    #[TestWith(['', []])]
    #[TestWith(['a:3:{i:0;s:2:"12";i:1;i:4;i:2;s:2:"12";}', [12, 4]])]
    #[TestWith(['a:2:{i:0;s:1:"0";i:1;s:2:"-3";}', []])]
    #[TestWith(['O:8:"stdClass":0:{}', []])]
    #[TestWith([7, []])]
    public function testPageIdsKeepTheOrderAndDropJunk(mixed $value, array $expected): void
    {
        $this->assertSame($expected, ConsentConfig::pageIds($value));
    }

    #[TestWith([['layout' => 'card', 'position' => 'top-right'], 'top-right'])]
    #[TestWith([['layout' => 'card', 'position' => 'center'], 'center'])]
    #[TestWith([['layout' => 'card', 'position' => 'top'], 'bottom-left'])]
    #[TestWith([['layout' => 'card', 'position' => '<x>'], 'bottom-left'])]
    #[TestWith([['layout' => 'card'], 'bottom-left'])]
    #[TestWith([['layout' => 'bar', 'barPosition' => 'top', 'position' => 'center'], 'top'])]
    #[TestWith([['layout' => 'bar', 'barPosition' => 'top-left'], 'bottom'])]
    #[TestWith([['layout' => 'bar'], 'bottom'])]
    #[TestWith([[], 'bottom-left'])]
    public function testPositionFallsBackToTheDefaultOfTheLayout(array $banner, string $expected): void
    {
        $this->assertSame($expected, ConsentConfig::position($banner));
    }

    #[TestWith([365, 365])]
    #[TestWith(['30', 30])]
    #[TestWith([1, 1])]
    #[TestWith([395, 395])]
    #[TestWith([396, 365])]
    #[TestWith([0, 365])]
    #[TestWith([-5, 365])]
    #[TestWith(['1.5', 365])]
    #[TestWith(['abc', 365])]
    #[TestWith([null, 365])]
    public function testLifetimeStaysWithinThirteenMonths(mixed $value, int $expected): void
    {
        $this->assertSame($expected, ConsentConfig::lifetime($value));
    }

    public function testThePageLookupReadsTheBannerOfTheRootPage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->with($this->stringContains('tl_page'), [4])->willReturn('7');
        $connection->method('fetchAssociative')->willReturn(['id' => 7, 'version' => 2, 'headline' => 'H', 'text' => '', 'placeholderText' => '', 'askOnFirstVisit' => 1, 'privacyPage' => 0, 'imprintPage' => 0, 'excludePages' => serialize(['12'])]);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls([['id' => 3, 'alias' => 'notwendig', 'title' => 'N', 'description' => '', 'essential' => 1]], []);

        $banner = $this->config($connection)->bannerForPage($this->page(12, 4));

        $this->assertSame(7, $banner['id']);
        $this->assertSame('notwendig', $banner['groups'][0]['alias']);
        // As the page shows it: page 12 is excluded
        $this->assertTrue($banner['quiet']);
    }

    public function testThePageLookupWithoutABannerIsNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects($this->never())->method('fetchAssociative');

        $this->assertNull($this->config($connection)->bannerForPage($this->page(2, 1)));
    }

    public function testCodePositionAndOrderFallBackToTheDefaults(): void
    {
        $service = ['pid' => 3, 'title' => 'S', 'provider' => '', 'description' => '', 'privacyUrl' => '', 'cookies' => "_ga*: 2 years\n\n_gid", 'hosts' => '', 'code' => ''];
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['id' => 7, 'version' => 1, 'headline' => 'H', 'text' => '', 'placeholderText' => '', 'askOnFirstVisit' => 1, 'privacyPage' => 0, 'imprintPage' => 0]);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [['id' => 3, 'alias' => 'a', 'title' => 'A', 'description' => '', 'essential' => 0]],
            [
                ['id' => 1, 'codePosition' => 'head', 'loadOrder' => '4'] + $service,
                ['id' => 2, 'codePosition' => '<evil>', 'loadOrder' => '-3'] + $service,
                ['id' => 3] + $service,
            ],
        );

        $services = $this->config($connection)->banner(7)['groups'][0]['services'];

        $this->assertSame([['head', 4], ['body', 0], ['body', 0]], array_map(static fn (array $s): array => [$s['position'], $s['order']], $services));
        $this->assertSame([['name' => '_ga*', 'duration' => '2 years'], ['name' => '_gid', 'duration' => '']], $services[0]['cookies']);
    }

    private function config(Connection $connection): ConsentConfig
    {
        $insertTags = $this->createStub(InsertTagParser::class);
        $insertTags->method('replaceInline')->willReturnArgument(0);

        return new ConsentConfig($connection, $this->createStub(ContentUrlGenerator::class), $insertTags);
    }

    private function page(int $id, int $rootId): PageModel
    {
        $page = $this->createStub(PageModel::class);
        $page->method('loadDetails')->willReturnSelf();
        $page->method('__get')->willReturnMap([['id', $id], ['rootId', $rootId]]);

        return $page;
    }
}

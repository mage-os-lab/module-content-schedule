<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use MageOS\ContentSchedule\Cron\Apply;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Test\Fixture\Block as BlockFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the cron publishes and unpublishes CMS pages and blocks at their dates.
 */
#[DbIsolation(true)]
class ApplyTest extends TestCase
{
    /**
     * A disabled page whose "Publish at" is past is enabled, and the date is cleared.
     */
    #[DataFixture(PageFixture::class, ['active' => false], 'page')]
    public function testPageIsPublishedAndItsDateCleared(): void
    {
        $pageId = $this->schedule('publish_at');

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertTrue((bool) $page->isActive());
        $this->assertNull($page->getData('publish_at'));
    }

    /**
     * An enabled page whose "Unpublish at" is past is disabled.
     */
    #[DataFixture(PageFixture::class, ['active' => true], 'page')]
    public function testPageIsUnpublished(): void
    {
        $pageId = $this->schedule('unpublish_at');

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertFalse((bool) $page->isActive());
    }

    /**
     * A disabled block whose "Publish at" is past is enabled, and the date is cleared.
     */
    #[DataFixture(BlockFixture::class, ['active' => false], 'block')]
    public function testBlockIsPublishedAndItsDateCleared(): void
    {
        $blockId = $this->schedule('publish_at', 'block');

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $block = Bootstrap::getObjectManager()->get(BlockRepositoryInterface::class)->getById($blockId);
        $this->assertTrue((bool) $block->isActive());
        $this->assertNull($block->getData('publish_at'));
    }

    /**
     * An enabled block whose "Unpublish at" is past is disabled, and the date is cleared.
     */
    #[DataFixture(BlockFixture::class, ['active' => true], 'block')]
    public function testBlockIsUnpublished(): void
    {
        $blockId = $this->schedule('unpublish_at', 'block');

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $block = Bootstrap::getObjectManager()->get(BlockRepositoryInterface::class)->getById($blockId);
        $this->assertFalse((bool) $block->isActive());
        $this->assertNull($block->getData('unpublish_at'));
    }

    /**
     * Both dates past, publishing the later one: the page ends up enabled and both dates are cleared.
     */
    #[DataFixture(PageFixture::class, ['active' => true], 'page')]
    public function testLaterPublishWinsWhenBothDatesArePast(): void
    {
        $this->schedule('unpublish_at', 'page', 120);
        $pageId = $this->schedule('publish_at', 'page', 60);

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertTrue((bool) $page->isActive());
        $this->assertNull($page->getData('publish_at'));
        $this->assertNull($page->getData('unpublish_at'));
    }

    /**
     * Both dates past, unpublishing the later one: the page ends up disabled.
     */
    #[DataFixture(PageFixture::class, ['active' => false], 'page')]
    public function testLaterUnpublishWinsWhenBothDatesArePast(): void
    {
        $this->schedule('publish_at', 'page', 120);
        $pageId = $this->schedule('unpublish_at', 'page', 60);

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertFalse((bool) $page->isActive());
    }

    /**
     * A page that cannot be saved (numeric URL key) is skipped, and the block after it is still published.
     *
     * No DB isolation: inside the test transaction, the page's rolled-back save would make the block's save fail too.
     */
    #[
        DbIsolation(false),
        DataFixture(PageFixture::class, ['active' => false], 'page'),
        DataFixture(BlockFixture::class, ['active' => false], 'block'),
    ]
    public function testUnsavablePageDoesNotBlockTheOthers(): void
    {
        $pageId = $this->schedule('publish_at');
        $blockId = $this->schedule('publish_at', 'block');
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_page'),
            ['identifier' => '12345'],
            ['page_id = ?' => $pageId]
        );

        $failed = Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $this->assertSame(1, $failed);
        $block = Bootstrap::getObjectManager()->get(BlockRepositoryInterface::class)->getById($blockId);
        $this->assertTrue((bool) $block->isActive());
        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertFalse((bool) $page->isActive());
    }

    /**
     * Notifying on: one summary e-mail lists the published page.
     */
    #[
        Config('cms/mageos_content_schedule/notify', '1'),
        Config('cms/mageos_content_schedule/notify_to', 'editor@example.com'),
        DataFixture(PageFixture::class, ['active' => false, 'title' => 'Summer <sale>'], 'page'),
    ]
    public function testSummaryIsEmailed(): void
    {
        $this->schedule('publish_at');

        Bootstrap::getObjectManager()->get(Apply::class)->execute();

        $message = Bootstrap::getObjectManager()->get(TransportBuilderMock::class)->getSentMessage();
        $this->assertNotNull($message);
        $this->assertSame('editor@example.com', $message->getTo()[0]->getEmail());
        $body = quoted_printable_decode($message->getBody()->bodyToString());
        $this->assertStringContainsString('Summer &lt;sale&gt;', $body);
        $this->assertStringContainsString('published', $body);
    }

    /**
     * Set the given date of the fixture page or block in the past and return its ID.
     *
     * @param string $field
     * @param string $fixture "page" or "block"
     * @param int $secondsAgo
     * @return int
     */
    private function schedule(string $field, string $fixture = 'page', int $secondsAgo = 60): int
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get($fixture)->getId();
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_' . $fixture),
            [$field => gmdate('Y-m-d H:i:s', time() - $secondsAgo)],
            [$fixture . '_id = ?' => $id]
        );
        return $id;
    }
}

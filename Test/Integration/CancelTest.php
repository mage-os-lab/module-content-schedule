<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Test\Fixture\Block as BlockFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Tests the "Cancel schedule" mass action of the CMS page and block grids.
 */
#[DbIsolation(true)]
class CancelTest extends AbstractBackendController
{
    /**
     * Both dates of the selected page are cleared, and its other data is kept.
     */
    #[DataFixture(PageFixture::class, ['active' => false], 'page')]
    public function testPageScheduleIsCancelled(): void
    {
        $pageId = $this->schedule('page');

        $this->cancel('cms_page_listing', $pageId);

        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertNull($page->getData('publish_at'));
        $this->assertNull($page->getData('unpublish_at'));
        $this->assertFalse((bool) $page->isActive());
        $this->assertRedirect($this->stringContains('cms/page'));
    }

    /**
     * Posted from the block grid, the mass action clears the dates of the selected block.
     */
    #[DataFixture(BlockFixture::class, ['active' => true], 'block')]
    public function testBlockScheduleIsCancelled(): void
    {
        $blockId = $this->schedule('block');

        $this->cancel('cms_block_listing', $blockId);

        $block = Bootstrap::getObjectManager()->get(BlockRepositoryInterface::class)->getById($blockId);
        $this->assertNull($block->getData('publish_at'));
        $this->assertNull($block->getData('unpublish_at'));
        $this->assertRedirect($this->stringContains('cms/block'));
    }

    /**
     * Post the mass action for one item of the given grid.
     *
     * @param string $namespace
     * @param int $id
     */
    private function cancel(string $namespace, int $id): void
    {
        $this->getRequest()->setMethod(Http::METHOD_POST)
            ->setPostValue(['namespace' => $namespace, 'selected' => [$id]]);
        $this->dispatch('backend/mageos_schedule/schedule/cancel');
    }

    /**
     * Set both dates of the fixture page or block in the future and return its ID.
     *
     * @param string $fixture "page" or "block"
     * @return int
     */
    private function schedule(string $fixture): int
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get($fixture)->getId();
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_' . $fixture),
            ['publish_at' => '2099-01-01 00:00:00', 'unpublish_at' => '2099-02-01 00:00:00'],
            [$fixture . '_id = ?' => $id]
        );
        return $id;
    }
}

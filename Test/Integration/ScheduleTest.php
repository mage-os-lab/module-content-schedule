<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Tests the "Schedule" mass action of the CMS page and block grids.
 */
#[DbIsolation(true)]
class ScheduleTest extends AbstractBackendController
{
    /**
     * The grid selection opens the date form with the selected IDs.
     */
    #[DataFixture(PageFixture::class, ['active' => false], 'page')]
    public function testSelectionOpensTheDateForm(): void
    {
        $pageId = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $this->getRequest()->setMethod(Http::METHOD_POST)
            ->setPostValue(['namespace' => 'cms_page_listing', 'selected' => [$pageId]]);

        $this->dispatch('backend/mageos_schedule/schedule/massSchedule');

        $body = $this->getResponse()->getBody();
        $this->assertStringContainsString('name="ids" value="' . $pageId . '"', $body);
        $this->assertStringContainsString('type="datetime-local"', $body);
    }

    /**
     * The posted "Publish at" is set on the page, and the empty "Unpublish at" leaves its date alone.
     */
    #[DataFixture(PageFixture::class, ['active' => false], 'page')]
    public function testDatesAreSetOnTheSelectedPages(): void
    {
        $pageId = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $this->getRequest()->setMethod(Http::METHOD_POST)->setPostValue([
            'namespace' => 'cms_page_listing',
            'ids' => (string) $pageId,
            'publish_at' => '2099-01-01T10:00',
            'unpublish_at' => '',
        ]);

        $this->dispatch('backend/mageos_schedule/schedule/save');

        $page = Bootstrap::getObjectManager()->get(PageRepositoryInterface::class)->getById($pageId);
        $this->assertStringStartsWith('2099-01-01', (string) $page->getData('publish_at'));
        $this->assertNull($page->getData('unpublish_at'));
        $this->assertRedirect($this->stringContains('cms/page'));
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use Laminas\Http\Headers;
use Magento\Cms\Test\Fixture\Block as BlockFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\Acl\Builder;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Tests the "Scheduled Content" admin grid: the page and the rows its grid loads from mui/index/render.
 */
#[DbIsolation(true)]
class UpcomingTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_ContentSchedule::upcoming';

    /**
     * @var string
     */
    protected $uri = 'backend/mageos_schedule/schedule/index';

    /**
     * A scheduled page is listed once per date, with its title.
     */
    #[DataFixture(PageFixture::class, ['active' => false, 'title' => 'Scheduled summer sale'], 'page')]
    public function testScheduledPageIsListed(): void
    {
        $pageId = $this->schedule('page');

        $rows = $this->rows($this->render(), 'page', $pageId);

        $this->assertSame(['publish', 'unpublish'], array_column($rows, 'action'));
        $this->assertSame(['Scheduled summer sale', 'Scheduled summer sale'], array_column($rows, 'title'));
    }

    /**
     * An admin user without access to CMS pages does not see them listed, but still sees the blocks.
     */
    #[
        DataFixture(PageFixture::class, ['active' => false, 'title' => 'Scheduled summer sale'], 'page'),
        DataFixture(BlockFixture::class, ['active' => false, 'title' => 'Scheduled summer banner'], 'block'),
    ]
    public function testPagesAreHiddenWithoutPageAccess(): void
    {
        $pageId = $this->schedule('page');
        $blockId = $this->schedule('block');
        $this->_objectManager->get(Builder::class)->getAcl()
            ->deny($this->_auth->getUser()->getRoles(), 'Magento_Cms::page');

        $items = $this->render();

        $this->assertSame([], $this->rows($items, 'page', $pageId));
        $this->assertSame(['publish', 'unpublish'], array_column($this->rows($items, 'block', $blockId), 'action'));
    }

    /**
     * Drop the page access denied by a test, so the next tests run with the full ACL.
     */
    protected function tearDown(): void
    {
        $this->_objectManager->get(Builder::class)->resetRuntimeAcl();
        parent::tearDown();
    }

    /**
     * The rows of the grid, as its JavaScript loads them from mui/index/render.
     *
     * @return array<int,array<string,mixed>>
     */
    private function render(): array
    {
        $this->getRequest()->setHeaders(Headers::fromString('Accept: application/json'));
        $this->getRequest()->setParams([
            'namespace' => 'mageos_content_schedule_upcoming_listing',
            'isAjax' => 'true',
            'paging' => ['pageSize' => 1000, 'current' => 1],
        ]);
        $this->dispatch('backend/mui/index/render');

        $data = json_decode($this->getResponse()->getBody(), true);
        $this->assertIsArray($data, 'The grid did not answer with JSON: ' . $this->getResponse()->getBody());
        $this->assertArrayHasKey('items', $data, 'The grid answered without rows: ' . $this->getResponse()->getBody());
        return $data['items'];
    }

    /**
     * The rows of one page or block, sorted by action.
     *
     * @param array<int,array<string,mixed>> $items
     * @param string $type "page" or "block"
     * @param int $id
     * @return array<int,array<string,mixed>>
     */
    private function rows(array $items, string $type, int $id): array
    {
        $rows = array_values(array_filter(
            $items,
            fn (array $row): bool => $row['entity_type'] === $type && (int) $row['entity_id'] === $id
        ));
        usort($rows, fn (array $a, array $b): int => $a['action'] <=> $b['action']);
        return $rows;
    }

    /**
     * Set both dates of the fixture page or block in the future.
     *
     * @param string $fixture "page" or "block"
     * @return int The page or block ID
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

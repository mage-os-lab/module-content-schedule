<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\Acl\Builder;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Tests the "Scheduled Content" admin page.
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
     * A scheduled page is listed with its title and both dates.
     */
    #[DataFixture(PageFixture::class, ['active' => false, 'title' => 'Scheduled summer sale'], 'page')]
    public function testScheduledPageIsListed(): void
    {
        $this->schedule();

        $this->dispatch($this->uri);

        $body = $this->getResponse()->getBody();
        $this->assertStringContainsString('Scheduled summer sale', $body);
        $this->assertStringContainsString('Unpublish', $body);
        $this->assertStringContainsString('2099', $body);
    }

    /**
     * An admin user without access to CMS pages does not see them listed.
     */
    #[DataFixture(PageFixture::class, ['active' => false, 'title' => 'Scheduled summer sale'], 'page')]
    public function testPagesAreHiddenWithoutPageAccess(): void
    {
        $this->schedule();
        $this->_objectManager->get(Builder::class)->getAcl()
            ->deny($this->_auth->getUser()->getRoles(), 'Magento_Cms::page');

        $this->dispatch($this->uri);

        $this->assertStringNotContainsString('Scheduled summer sale', $this->getResponse()->getBody());
    }

    /**
     * Set both dates of the fixture page in the future.
     */
    private function schedule(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_page'),
            ['publish_at' => '2099-01-01 00:00:00', 'unpublish_at' => '2099-02-01 00:00:00'],
            ['page_id = ?' => $id]
        );
    }
}

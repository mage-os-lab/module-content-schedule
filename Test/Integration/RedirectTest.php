<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Tests that a disabled CMS page with a "Redirect when disabled" target answers with a 301.
 */
#[DbIsolation(true), AppArea('frontend')]
class RedirectTest extends AbstractController
{
    /**
     * Redirect on: the disabled page redirects to its target path.
     */
    #[
        Config('cms/mageos_content_schedule/redirect', '1'),
        DataFixture(PageFixture::class, ['active' => false, 'identifier' => 'schedule-redirect'], 'page'),
    ]
    public function testDisabledPageRedirects(): void
    {
        $this->setTarget('summer-sale');

        $this->dispatch('/schedule-redirect');

        $this->assertSame(301, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringEndsWith('/summer-sale'));
    }

    /**
     * Redirect off: the disabled page is a 404, as without the module.
     */
    #[
        Config('cms/mageos_content_schedule/redirect', '0'),
        DataFixture(PageFixture::class, ['active' => false, 'identifier' => 'schedule-redirect-off'], 'page'),
    ]
    public function testNoRedirectWhenTurnedOff(): void
    {
        $this->setTarget('summer-sale');

        $this->dispatch('/schedule-redirect-off');

        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * A disabled page of another store view is a 404 here, even through its direct URL.
     */
    #[
        Config('cms/mageos_content_schedule/redirect', '1'),
        DataFixture(StoreFixture::class, as: 'other'),
        DataFixture(PageFixture::class, ['active' => false], 'page'),
    ]
    public function testNoRedirectFromAnotherStore(): void
    {
        $this->setTarget('summer-sale');
        $id = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_page_store'),
            ['store_id' => (int) DataFixtureStorageManager::getStorage()->get('other')->getId()],
            ['page_id = ?' => $id]
        );

        $this->dispatch('/cms/page/view/page_id/' . $id);

        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * Set the redirect target of the fixture page.
     *
     * @param string $target
     */
    private function setTarget(string $target): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('page')->getId();
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_page'),
            ['redirect_to' => $target],
            ['page_id = ?' => $id]
        );
    }
}

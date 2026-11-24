<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Integration;

use MageOS\ContentSchedule\Cron\Remind;
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
 * Tests the daily reminder of the dates to apply in the next 24 hours.
 */
#[DbIsolation(true)]
class RemindTest extends TestCase
{
    /**
     * The reminder lists the page due in two hours, not the block due in three days.
     */
    #[
        Config('cms/mageos_content_schedule/notify', '1'),
        Config('cms/mageos_content_schedule/notify_to', 'editor@example.com'),
        Config('cms/mageos_content_schedule/remind', '1'),
        DataFixture(PageFixture::class, ['active' => false, 'title' => 'Due tomorrow'], 'page'),
        DataFixture(BlockFixture::class, ['active' => false, 'title' => 'Due later'], 'block'),
    ]
    public function testReminderListsTheDatesOfTheNext24Hours(): void
    {
        $this->schedule('page', 7200);
        $this->schedule('block', 3 * 86400);

        Bootstrap::getObjectManager()->get(Remind::class)->execute();

        $message = Bootstrap::getObjectManager()->get(TransportBuilderMock::class)->getSentMessage();
        $this->assertNotNull($message);
        $this->assertSame('editor@example.com', $message->getTo()[0]->getEmail());
        $body = quoted_printable_decode($message->getBody()->bodyToString());
        $this->assertStringContainsString('Due tomorrow', $body);
        $this->assertStringNotContainsString('Due later', $body);
    }

    /**
     * Reminder off (the default): nothing is sent, even with a date to come.
     */
    #[
        Config('cms/mageos_content_schedule/notify', '1'),
        Config('cms/mageos_content_schedule/notify_to', 'editor@example.com'),
        DataFixture(PageFixture::class, ['active' => false], 'page'),
    ]
    public function testNoReminderWhenTurnedOff(): void
    {
        $this->schedule('page', 7200);

        Bootstrap::getObjectManager()->get(Remind::class)->execute();

        $this->assertNull(Bootstrap::getObjectManager()->get(TransportBuilderMock::class)->getSentMessage());
    }

    /**
     * Set "Publish at" of the fixture page or block the given number of seconds from now.
     *
     * @param string $fixture "page" or "block"
     * @param int $secondsAhead
     */
    private function schedule(string $fixture, int $secondsAhead): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_' . $fixture),
            ['publish_at' => gmdate('Y-m-d H:i:s', time() + $secondsAhead)],
            [$fixture . '_id = ?' => (int) DataFixtureStorageManager::getStorage()->get($fixture)->getId()]
        );
    }
}

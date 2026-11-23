<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Observer\WarnPastDates;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the warning shown when a CMS page or block is saved with a past schedule date.
 */
class WarnPastDatesTest extends TestCase
{
    /**
     * Two past dates give a single warning.
     */
    public function testPastDatesWarnOnce(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->once())->method('addWarningMessage');
        $page = new DataObject(['publish_at' => '2020-01-01 00:00:00', 'unpublish_at' => '2020-01-02 00:00:00']);

        (new WarnPastDates($messages))->execute(new Observer(['object' => $page]));
    }

    /**
     * Future and empty dates give no warning.
     */
    public function testFutureDatesDoNotWarn(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->never())->method('addWarningMessage');
        $block = new DataObject(['publish_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'unpublish_at' => null]);

        (new WarnPastDates($messages))->execute(new Observer(['object' => $block]));
    }
}

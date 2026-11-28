<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Observer\WarnPastDates;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Message\Warning;
use PHPUnit\Framework\TestCase;

/**
 * Tests the warning shown when a CMS page or block is saved with a past schedule date.
 */
class WarnPastDatesTest extends TestCase
{
    /**
     * @var MessageInterface[]
     */
    private array $added = [];

    /**
     * Two past dates give a single warning.
     */
    public function testPastDatesWarnOnce(): void
    {
        $page = new DataObject(['publish_at' => '2020-01-01 00:00:00', 'unpublish_at' => '2020-01-02 00:00:00']);

        (new WarnPastDates($this->messages()))->execute(new Observer(['object' => $page]));

        $this->assertCount(1, $this->added);
        $this->assertSame(MessageInterface::TYPE_WARNING, $this->added[0]->getType());
    }

    /**
     * Future and empty dates give no warning.
     */
    public function testFutureDatesDoNotWarn(): void
    {
        $block = new DataObject(['publish_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'unpublish_at' => null]);

        (new WarnPastDates($this->messages()))->execute(new Observer(['object' => $block]));

        $this->assertSame([], $this->added);
    }

    /**
     * A message manager that records the unique messages added.
     *
     * @return ManagerInterface
     */
    private function messages(): ManagerInterface
    {
        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('createMessage')->willReturnCallback(fn (): Warning => new Warning());
        $messages->method('addUniqueMessages')->willReturnCallback(
            function (array $added) use ($messages): ManagerInterface {
                $this->added = array_merge($this->added, $added);
                return $messages;
            }
        );
        return $messages;
    }
}

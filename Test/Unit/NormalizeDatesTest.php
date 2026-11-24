<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Observer\NormalizeDates;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * Tests the conversion of the posted schedule dates to the column format.
 */
class NormalizeDatesTest extends TestCase
{
    /**
     * UTC ISO 8601 values from the admin form are stored as UTC "Y-m-d H:i:s"; empty values are cleared.
     */
    public function testDatesAreStoredAsUtcAndEmptyOnesCleared(): void
    {
        $page = new DataObject(['publish_at' => '2026-12-01T08:30:00+01:00', 'unpublish_at' => '']);

        (new NormalizeDates())->execute(new Observer(['object' => $page]));

        $this->assertSame('2026-12-01 07:30:00', $page->getData('publish_at'));
        $this->assertNull($page->getData('unpublish_at'));
    }

    /**
     * An invalid date refuses the save instead of silently dropping the schedule.
     */
    public function testInvalidDateIsRefused(): void
    {
        $page = new DataObject(['publish_at' => 'not a date']);

        $this->expectException(LocalizedException::class);

        (new NormalizeDates())->execute(new Observer(['object' => $page]));
    }

    /**
     * A value already in the column format is kept as is, so saving twice does not shift it, whatever PHP's time zone.
     */
    public function testStoredValueIsKept(): void
    {
        $block = new DataObject(['publish_at' => '2026-12-01 07:30:00', 'unpublish_at' => '']);
        $timezone = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');

        try {
            (new NormalizeDates())->execute(new Observer(['object' => $block]));
        } finally {
            date_default_timezone_set($timezone);
        }

        $this->assertSame('2026-12-01 07:30:00', $block->getData('publish_at'));
        $this->assertNull($block->getData('unpublish_at'));
    }
}

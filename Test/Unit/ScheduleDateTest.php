<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Model\ScheduleDate;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the reading of the dates typed in the "Schedule" mass action form.
 */
class ScheduleDateTest extends TestCase
{
    /**
     * Dates typed in the admin time zone, and their UTC column value.
     *
     * @return array<string,array{string,?string}>
     */
    public static function dates(): array
    {
        return [
            'winter time' => ['2026-12-01T08:30', '2026-12-01 07:30:00'],
            'summer time' => ['2026-07-01T08:30', '2026-07-01 06:30:00'],
            'empty' => [' ', null],
        ];
    }

    /**
     * A date typed in the admin time zone is stored in UTC, daylight saving included; an empty one is no date.
     *
     * @param string $typed
     * @param string|null $utc
     */
    #[DataProvider('dates')]
    public function testDateIsReadInTheAdminTimeZone(string $typed, ?string $utc): void
    {
        $this->assertSame($utc, $this->scheduleDate()->toUtc($typed, 'Publish at'));
    }

    /**
     * A value that is not a date is refused instead of being ignored.
     */
    public function testInvalidDateIsRefused(): void
    {
        $this->expectException(LocalizedException::class);

        $this->scheduleDate()->toUtc('tomorrow-ish', 'Publish at');
    }

    /**
     * The service with the admin time zone set to Paris.
     *
     * @return ScheduleDate
     */
    private function scheduleDate(): ScheduleDate
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('getConfigTimezone')->willReturn('Europe/Paris');
        return new ScheduleDate($timezone);
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Reads a schedule date typed in the admin time zone, such as "2026-12-01T08:30", as the UTC column value.
 */
class ScheduleDate
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * The UTC "Y-m-d H:i:s" value of the given admin date; null when empty.
     *
     * @param string $value
     * @param \Stringable|string $label The field, for the error message
     * @return string|null
     * @throws LocalizedException When the value is not a date
     */
    public function toUtc(string $value, \Stringable|string $label): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone($this->timezone->getConfigTimezone()));
        } catch (Exception) {
            throw new LocalizedException(__('"%1" is not a valid date for "%2".', $value, $label));
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}

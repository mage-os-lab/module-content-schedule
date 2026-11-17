<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Observer;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Converts the schedule dates of a CMS page or block to the column format before it is saved.
 */
class NormalizeDates implements ObserverInterface
{
    /**
     * The admin date-time field posts a UTC ISO 8601 string, the column wants "Y-m-d H:i:s"; invalid dates are cleared.
     *
     * A value without an offset (already in the column format) is read as UTC, whatever PHP's default time zone.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getData('object');
        $utc = new DateTimeZone('UTC');
        foreach (['publish_at', 'unpublish_at'] as $field) {
            $value = $object->getData($field);
            try {
                $date = $value ? new DateTimeImmutable((string) $value, $utc) : null;
            } catch (Exception) {
                $date = null;
            }
            $object->setData($field, $date?->setTimezone($utc)->format('Y-m-d H:i:s'));
        }
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Observer;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Converts the schedule dates of a CMS page or block to the column format before it is saved.
 */
class NormalizeDates implements ObserverInterface
{
    /**
     * The admin date-time field posts a UTC ISO 8601 string, the column wants "Y-m-d H:i:s".
     *
     * An invalid date is refused. A value without an offset (already in the column format) is read as UTC, whatever
     * PHP's default time zone.
     *
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getData('object');
        $utc = new DateTimeZone('UTC');
        foreach (['publish_at' => __('Publish at'), 'unpublish_at' => __('Unpublish at')] as $field => $label) {
            $value = $object->getData($field);
            try {
                $date = $value ? new DateTimeImmutable((string) $value, $utc) : null;
            } catch (Exception) {
                throw new LocalizedException(__('"%1" is not a valid date for "%2".', $value, $label));
            }
            $object->setData($field, $date?->setTimezone($utc)->format('Y-m-d H:i:s'));
        }
    }
}

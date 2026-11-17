<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Observer;

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
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getData('object');
        foreach (['publish_at', 'unpublish_at'] as $field) {
            $value = $object->getData($field);
            $time = $value ? strtotime((string) $value) : false;
            $object->setData($field, $time === false ? null : gmdate('Y-m-d H:i:s', $time));
        }
    }
}

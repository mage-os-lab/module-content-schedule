<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Warns the admin user who saves a CMS page or block with a schedule date already past.
 *
 * A past date is allowed on purpose: it applies at the next cron run, which is a way to publish soon.
 */
class WarnPastDates implements ObserverInterface
{
    /**
     * @param ManagerInterface $messageManager
     */
    public function __construct(private readonly ManagerInterface $messageManager)
    {
    }

    /**
     * Runs after save, when NormalizeDates has stored the dates as UTC "Y-m-d H:i:s", which compare as strings.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getData('object');
        $now = gmdate('Y-m-d H:i:s');
        foreach (['publish_at', 'unpublish_at'] as $field) {
            $value = $object->getData($field);
            if ($value !== null && $value <= $now) {
                $this->messageManager->addWarningMessage(
                    __('A schedule date is already past: it will be applied at the next cron run, within a minute.')
                );
                return;
            }
        }
    }
}

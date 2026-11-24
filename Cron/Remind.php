<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Cron;

use DateTime;
use DateTimeZone;
use IntlDateFormatter;
use MageOS\ContentSchedule\Model\Notifier;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Once a day, e-mails the publish / unpublish dates that will be applied in the next 24 hours.
 */
class Remind
{
    private const FIELDS = ['publish_at', 'unpublish_at'];

    /**
     * @param PageCollectionFactory $pages
     * @param BlockCollectionFactory $blocks
     * @param TimezoneInterface $timezone
     * @param Notifier $notifier
     */
    public function __construct(
        private readonly PageCollectionFactory $pages,
        private readonly BlockCollectionFactory $blocks,
        private readonly TimezoneInterface $timezone,
        private readonly Notifier $notifier
    ) {
    }

    /**
     * List the dates after now and up to 24 hours from now, soonest first, in the admin time zone.
     *
     * Dates already due are left out: the "apply" job handles them within a minute and reports its failures.
     */
    public function execute(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $until = gmdate('Y-m-d H:i:s', time() + 86400);
        $window = ['from' => $now, 'to' => $until];
        $lines = [];
        foreach ([[__('CMS page'), $this->pages->create()], [__('CMS block'), $this->blocks->create()]] as $source) {
            [$type, $collection] = $source;
            foreach ($collection->addFieldToFilter(self::FIELDS, [$window, $window]) as $item) {
                foreach (self::FIELDS as $field) {
                    $date = (string) $item->getData($field);
                    if ($date <= $now || $date > $until) {
                        continue;
                    }
                    $lines[] = [$date, (string) __(
                        '%1 — %2: %3 "%4" (ID %5)',
                        $this->timezone->formatDateTime(
                            new DateTime($date, new DateTimeZone('UTC')),
                            IntlDateFormatter::MEDIUM,
                            IntlDateFormatter::SHORT
                        ),
                        $field === 'publish_at' ? __('Publish') : __('Unpublish'),
                        $type,
                        $item->getData('title'),
                        $item->getId()
                    )];
                }
            }
        }
        usort($lines, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $this->notifier->remind(array_column($lines, 1));
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\ViewModel;

use DateTime;
use DateTimeZone;
use IntlDateFormatter;
use Magento\Backend\Model\UrlInterface;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Rows of the "Scheduled Content" page: one per date still to apply, soonest first.
 *
 * @deprecated 1.3.0 The page is now a UI grid with filters and paging; kept for layouts that used it.
 * @see \MageOS\ContentSchedule\Model\ResourceModel\Upcoming\Collection
 */
class Upcoming implements ArgumentInterface
{
    /**
     * @param PageCollectionFactory $pages
     * @param BlockCollectionFactory $blocks
     * @param TimezoneInterface $timezone
     * @param UrlInterface $url
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly PageCollectionFactory $pages,
        private readonly BlockCollectionFactory $blocks,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Each date in UTC (to sort) and in the admin time zone, with the item's title, identifier, status and edit URL.
     *
     * Pages and blocks are listed only to admin users allowed to manage them, as in the core CMS grids.
     *
     * @return array<int,array{utc:string,date:string,action:string,type:string,title:string,identifier:string,
     *     active:bool,url:string}>
     */
    public function getRows(): array
    {
        $scheduled = [['notnull' => true], ['notnull' => true]];
        $sources = [];
        if ($this->authorization->isAllowed('Magento_Cms::page')) {
            $sources[] = [__('Page'), $this->pages->create(), 'cms/page/edit', 'page_id'];
        }
        if ($this->authorization->isAllowed('Magento_Cms::block')) {
            $sources[] = [__('Block'), $this->blocks->create(), 'cms/block/edit', 'block_id'];
        }
        $rows = [];
        foreach ($sources as [$type, $collection, $route, $param]) {
            foreach ($collection->addFieldToFilter(['publish_at', 'unpublish_at'], $scheduled) as $item) {
                foreach (['publish_at' => __('Publish'), 'unpublish_at' => __('Unpublish')] as $field => $action) {
                    $date = $item->getData($field);
                    if ($date === null) {
                        continue;
                    }
                    $rows[] = [
                        'utc' => (string) $date,
                        'date' => $this->timezone->formatDateTime(
                            new DateTime((string) $date, new DateTimeZone('UTC')),
                            IntlDateFormatter::MEDIUM,
                            IntlDateFormatter::SHORT
                        ),
                        'action' => (string) $action,
                        'type' => (string) $type,
                        'title' => (string) $item->getData('title'),
                        'identifier' => (string) $item->getData('identifier'),
                        'active' => (bool) $item->getData('is_active'),
                        'url' => $this->url->getUrl($route, [$param => $item->getId()]),
                    ];
                }
            }
        }
        usort($rows, fn (array $a, array $b): int => $a['utc'] <=> $b['utc']);
        return $rows;
    }
}

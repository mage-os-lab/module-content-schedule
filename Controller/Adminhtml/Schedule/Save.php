<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Controller\Adminhtml\Schedule;

use MageOS\ContentSchedule\Model\ScheduleDate;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Sets the dates posted by the "Schedule" form on the CMS pages or blocks chosen in the grid.
 *
 * A date left empty keeps each item's current one: to clear dates, use "Cancel schedule".
 */
class Save extends Action implements HttpPostActionInterface
{
    private const BLOCK_GRID = 'cms_block_listing';

    /**
     * @param Context $context
     * @param ScheduleDate $scheduleDate
     * @param PageCollectionFactory $pages
     * @param PageRepositoryInterface $pageRepository
     * @param BlockCollectionFactory $blocks
     * @param BlockRepositoryInterface $blockRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly ScheduleDate $scheduleDate,
        private readonly PageCollectionFactory $pages,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockCollectionFactory $blocks,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Save the dates on each item on its own, so one failure does not stop the others, then go back to the grid.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $isBlock = $this->isBlockGrid();
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath($isBlock ? 'cms/block/' : 'cms/page/');
        try {
            $dates = array_filter([
                'publish_at' => $this->scheduleDate->toUtc(
                    (string) $this->getRequest()->getParam('publish_at'),
                    __('Publish at')
                ),
                'unpublish_at' => $this->scheduleDate->toUtc(
                    (string) $this->getRequest()->getParam('unpublish_at'),
                    __('Unpublish at')
                ),
            ]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect;
        }
        $ids = array_filter(array_map('intval', explode(',', (string) $this->getRequest()->getParam('ids'))));
        if (!$dates || !$ids) {
            $this->messageManager->addErrorMessage(__('Enter a Publish at or Unpublish at date.'));
            return $redirect;
        }
        $collection = $isBlock ? $this->blocks->create() : $this->pages->create();
        $collection->addFieldToFilter($isBlock ? 'block_id' : 'page_id', ['in' => $ids]);
        $scheduled = 0;
        $failed = 0;
        foreach ($collection as $item) {
            $item->addData($dates);
            try {
                if ($item instanceof Block) {
                    $this->blockRepository->save($item);
                } elseif ($item instanceof Page) {
                    $this->pageRepository->save($item);
                }
                $scheduled++;
            } catch (LocalizedException $e) {
                $failed++;
                $this->logger->error(
                    sprintf(
                        'Content schedule: CMS %s %s not saved: %s',
                        $isBlock ? 'block' : 'page',
                        $item->getId(),
                        $e->getMessage()
                    )
                );
            }
        }
        $this->messageManager->addSuccessMessage(__('%1 item(s) have been scheduled.', $scheduled));
        if ($failed) {
            $this->messageManager->addErrorMessage(__('%1 item(s) could not be saved: see the log.', $failed));
        }
        return $redirect;
    }

    /**
     * Same ACL resources as the core mass actions of each grid.
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed($this->isBlockGrid() ? 'Magento_Cms::block' : 'Magento_Cms::save');
    }

    /**
     * Whether the form was opened from the CMS block grid rather than the page grid.
     *
     * @return bool
     */
    private function isBlockGrid(): bool
    {
        return $this->getRequest()->getParam('namespace') === self::BLOCK_GRID;
    }
}

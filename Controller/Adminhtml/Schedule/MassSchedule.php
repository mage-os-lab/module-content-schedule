<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Controller\Adminhtml\Schedule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Mass action of the CMS page and block grids: asks the dates to set on the selected items, then posts them to Save.
 */
class MassSchedule extends Action implements HttpPostActionInterface
{
    private const BLOCK_GRID = 'cms_block_listing';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param PageCollectionFactory $pages
     * @param BlockCollectionFactory $blocks
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly PageCollectionFactory $pages,
        private readonly BlockCollectionFactory $blocks
    ) {
        parent::__construct($context);
    }

    /**
     * Show the date form for the IDs of the selection.
     *
     * @return Page
     * @throws LocalizedException
     */
    public function execute(): Page
    {
        $isBlock = $this->isBlockGrid();
        $ids = $this->filter->getCollection($isBlock ? $this->blocks->create() : $this->pages->create())->getAllIds();
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu($isBlock ? 'Magento_Cms::cms_block' : 'Magento_Cms::cms_page');
        $page->getConfig()->getTitle()->prepend((string) __('Schedule %1 item(s)', count($ids)));
        $form = $page->getLayout()->getBlock('mageos_content_schedule_mass');
        if ($form instanceof AbstractBlock) {
            $form->setData('ids', implode(',', $ids));
            $form->setData('namespace', $isBlock ? self::BLOCK_GRID : 'cms_page_listing');
        }
        return $page;
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
     * Whether the mass action was posted from the CMS block grid rather than the page grid.
     *
     * @return bool
     */
    private function isBlockGrid(): bool
    {
        return $this->getRequest()->getParam('namespace') === self::BLOCK_GRID;
    }
}

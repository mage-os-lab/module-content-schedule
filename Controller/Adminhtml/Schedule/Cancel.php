<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Controller\Adminhtml\Schedule;

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
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

/**
 * Mass action of the CMS page and block grids: clears "Publish at" and "Unpublish at" of the selected items.
 *
 * The grid comes from the "namespace" parameter the mass action posts, which the selection filter reads too.
 */
class Cancel extends Action implements HttpPostActionInterface
{
    private const BLOCK_GRID = 'cms_block_listing';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param PageCollectionFactory $pages
     * @param PageRepositoryInterface $pageRepository
     * @param BlockCollectionFactory $blocks
     * @param BlockRepositoryInterface $blockRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly PageCollectionFactory $pages,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockCollectionFactory $blocks,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Clear the dates of the selected scheduled items, each saved on its own so one failure does not stop the others.
     *
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute(): Redirect
    {
        $isBlock = $this->isBlockGrid();
        $collection = $this->filter->getCollection($isBlock ? $this->blocks->create() : $this->pages->create());
        $collection->addFieldToFilter(
            ['publish_at', 'unpublish_at'],
            [['notnull' => true], ['notnull' => true]]
        );
        $cancelled = 0;
        $failed = 0;
        foreach ($collection as $item) {
            $item->setData('publish_at', null);
            $item->setData('unpublish_at', null);
            try {
                if ($item instanceof Block) {
                    $this->blockRepository->save($item);
                } elseif ($item instanceof Page) {
                    $this->pageRepository->save($item);
                }
                $cancelled++;
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
        $this->messageManager->addSuccessMessage(__('The schedule of %1 item(s) has been cancelled.', $cancelled));
        if ($failed) {
            $this->messageManager->addErrorMessage(__('%1 item(s) could not be saved: see the log.', $failed));
        }
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setPath($isBlock ? 'cms/block/' : 'cms/page/');
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

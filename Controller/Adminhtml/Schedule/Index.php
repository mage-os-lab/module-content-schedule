<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Controller\Adminhtml\Schedule;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Lists the upcoming publish and unpublish dates of CMS pages and blocks, soonest first.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_ContentSchedule::upcoming';

    /**
     * @inheritdoc
     */
    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(self::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend((string) __('Scheduled Content'));
        return $page;
    }
}

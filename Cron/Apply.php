<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Cron;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * "publish_at" / "unpublish_at" are one-shot: once the date is reached the entity is enabled / disabled
 * and the date is cleared, so a later manual change is never overridden.
 */
class Apply
{
    private const FIELDS = ['publish_at', 'unpublish_at'];

    /**
     * @param PageCollectionFactory $pages
     * @param PageRepositoryInterface $pageRepository
     * @param BlockCollectionFactory $blocks
     * @param BlockRepositoryInterface $blockRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PageCollectionFactory $pages,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockCollectionFactory $blocks,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Apply the due dates of every CMS page and block, saving each entity once.
     *
     * An entity that cannot be saved is logged and skipped, so it does not hold back the others.
     */
    public function execute(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $due = [['lteq' => $now], ['lteq' => $now]];
        foreach ($this->pages->create()->addFieldToFilter(self::FIELDS, $due)->getAllIds() as $id) {
            try {
                $page = $this->pageRepository->getById($id);
                if ($page instanceof Page) {
                    $this->schedule($page, $now);
                    $this->pageRepository->save($page);
                }
            } catch (LocalizedException $e) {
                $this->logger->error(sprintf('Content schedule: CMS page %s not saved: %s', $id, $e->getMessage()));
            }
        }
        foreach ($this->blocks->create()->addFieldToFilter(self::FIELDS, $due)->getAllIds() as $id) {
            try {
                $block = $this->blockRepository->getById($id);
                if ($block instanceof Block) {
                    $this->schedule($block, $now);
                    $this->blockRepository->save($block);
                }
            } catch (LocalizedException $e) {
                $this->logger->error(sprintf('Content schedule: CMS block %s not saved: %s', $id, $e->getMessage()));
            }
        }
    }

    /**
     * Set the state of the latest due date and clear the due dates; on equal dates, unpublishing wins.
     *
     * @param Page|Block $entity
     * @param string $now
     */
    private function schedule(Page|Block $entity, string $now): void
    {
        $publish = $entity->getData('publish_at');
        $unpublish = $entity->getData('unpublish_at');
        $publishDue = $publish !== null && $publish <= $now;
        $unpublishDue = $unpublish !== null && $unpublish <= $now;
        if (!$publishDue && !$unpublishDue) {
            return;
        }
        $entity->setIsActive($publishDue && (!$unpublishDue || $publish > $unpublish));
        if ($publishDue) {
            $entity->setData('publish_at', null);
        }
        if ($unpublishDue) {
            $entity->setData('unpublish_at', null);
        }
    }
}

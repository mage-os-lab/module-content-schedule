<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Cron;

use Exception;
use MageOS\ContentSchedule\Model\Notifier;
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
     * @param Notifier $notifier
     */
    public function __construct(
        private readonly PageCollectionFactory $pages,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockCollectionFactory $blocks,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly LoggerInterface $logger,
        private readonly Notifier $notifier
    ) {
    }

    /**
     * Apply the due dates of every CMS page and block, saving each entity once, then e-mail the summary.
     *
     * An entity that cannot be saved, whatever the exception, is logged and skipped, so it does not hold back the
     * others; it is tried again at each run, and the notifier e-mails it once.
     *
     * @return int The number of pages and blocks that could not be saved
     */
    public function execute(): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $due = [['lteq' => $now], ['lteq' => $now]];
        $changes = [];
        $failures = [];
        foreach ($this->pages->create()->addFieldToFilter(self::FIELDS, $due)->getAllIds() as $id) {
            try {
                $page = $this->pageRepository->getById($id);
                if (!$page instanceof Page) {
                    throw new LocalizedException(
                        __('Not a core CMS model (%1): the schedule cannot be applied.', $page::class)
                    );
                }
                if ($this->schedule($page, $now)) {
                    $this->pageRepository->save($page);
                    $changes[] = $this->describe(__('CMS page'), $page);
                }
            } catch (Exception $e) {
                $failures['page:' . $id] = sprintf('%s %s: %s', __('CMS page'), $id, $e->getMessage());
                $this->logger->error(sprintf('Content schedule: CMS page %s not saved: %s', $id, $e->getMessage()));
            }
        }
        foreach ($this->blocks->create()->addFieldToFilter(self::FIELDS, $due)->getAllIds() as $id) {
            try {
                $block = $this->blockRepository->getById($id);
                if (!$block instanceof Block) {
                    throw new LocalizedException(
                        __('Not a core CMS model (%1): the schedule cannot be applied.', $block::class)
                    );
                }
                if ($this->schedule($block, $now)) {
                    $this->blockRepository->save($block);
                    $changes[] = $this->describe(__('CMS block'), $block);
                }
            } catch (Exception $e) {
                $failures['block:' . $id] = sprintf('%s %s: %s', __('CMS block'), $id, $e->getMessage());
                $this->logger->error(sprintf('Content schedule: CMS block %s not saved: %s', $id, $e->getMessage()));
            }
        }
        $this->notifier->notify($changes, $failures);
        return count($failures);
    }

    /**
     * Set the state of the latest due date and clear the due dates; on equal dates, unpublishing wins.
     *
     * @param Page|Block $entity
     * @param string $now
     * @return bool whether a date was due
     */
    private function schedule(Page|Block $entity, string $now): bool
    {
        $publish = $entity->getData('publish_at');
        $unpublish = $entity->getData('unpublish_at');
        $publishDue = $publish !== null && $publish <= $now;
        $unpublishDue = $unpublish !== null && $unpublish <= $now;
        if (!$publishDue && !$unpublishDue) {
            return false;
        }
        $entity->setIsActive($publishDue && (!$unpublishDue || $publish > $unpublish));
        if ($publishDue) {
            $entity->setData('publish_at', null);
        }
        if ($unpublishDue) {
            $entity->setData('unpublish_at', null);
        }
        return true;
    }

    /**
     * One line of the summary e-mail, such as: CMS page "Summer sale" (ID 12): published.
     *
     * @param \Stringable $type
     * @param Page|Block $entity
     * @return string
     */
    private function describe(\Stringable $type, Page|Block $entity): string
    {
        return (string) __(
            '%1 "%2" (ID %3): %4',
            $type,
            $entity->getTitle(),
            $entity->getId(),
            $entity->isActive() ? __('published') : __('unpublished')
        );
    }
}

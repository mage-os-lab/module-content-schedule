<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Cron;

use Exception;
use MageOS\ContentSchedule\Model\Journal;
use MageOS\ContentSchedule\Model\Notifier;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
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
     * @param Journal $journal
     */
    public function __construct(
        private readonly PageCollectionFactory $pages,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockCollectionFactory $blocks,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly LoggerInterface $logger,
        private readonly Notifier $notifier,
        private readonly Journal $journal
    ) {
    }

    /**
     * Cron entry point: apply the due dates, logged as run by cron.
     *
     * @return int The number of pages and blocks that could not be saved
     */
    public function execute(): int
    {
        return $this->run(Journal::SOURCE_CRON);
    }

    /**
     * Apply the due dates of every CMS page and block, saving each entity once, then log them and e-mail the summary.
     *
     * An entity that cannot be saved, whatever the exception, is logged and skipped, so it does not hold back the
     * others; it is tried again at each run, and the notifier e-mails it once.
     *
     * @param string $source Who runs it, for the log: Journal::SOURCE_CRON or Journal::SOURCE_CLI
     * @return int The number of pages and blocks that could not be saved
     */
    public function run(string $source): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $due = [['lteq' => $now], ['lteq' => $now]];
        $changes = [];
        $failures = [];
        $entries = [];
        foreach ($this->pages->create()->addFieldToFilter(self::FIELDS, $due)->getAllIds() as $id) {
            $page = null;
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
                    $entries['page:' . $id] = $this->entry('page', (int) $id, $page);
                }
            } catch (Exception $e) {
                $failures['page:' . $id] = __('%1 %2: %3', __('CMS page'), $id, $this->reason($e));
                $this->logger->error(sprintf('Content schedule: CMS page %s not saved: %s', $id, $e->getMessage()));
                $entries['page:' . $id] = $this->entry('page', (int) $id, $page, $e->getMessage());
            }
        }
        foreach ($this->blocks->create()->addFieldToFilter(self::FIELDS, $due)->getAllIds() as $id) {
            $block = null;
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
                    $entries['block:' . $id] = $this->entry('block', (int) $id, $block);
                }
            } catch (Exception $e) {
                $failures['block:' . $id] = __('%1 %2: %3', __('CMS block'), $id, $this->reason($e));
                $this->logger->error(sprintf('Content schedule: CMS block %s not saved: %s', $id, $e->getMessage()));
                $entries['block:' . $id] = $this->entry('block', (int) $id, $block, $e->getMessage());
            }
        }
        $this->notifier->notify($changes, $failures);
        $this->journal->record($entries, $source);
        return count($failures);
    }

    /**
     * The error of a failure, for the e-mail: a Magento message stays a phrase, translated when the e-mail is built.
     *
     * @param Exception $e
     * @return Phrase|string
     */
    private function reason(Exception $e): Phrase|string
    {
        return $e instanceof LocalizedException
            ? new Phrase($e->getRawMessage(), $e->getParameters())
            : $e->getMessage();
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
     * One row of the log; on failure, the action is the one that was attempted, unknown if the entity did not load.
     *
     * @param string $type "page" or "block"
     * @param int $id
     * @param mixed $entity The loaded page or block, if any
     * @param string|null $error
     * @return array{entity_type:string,entity_id:int,title:?string,action:?string,result:string,message:?string}
     */
    private function entry(string $type, int $id, mixed $entity, ?string $error = null): array
    {
        $entity = $entity instanceof Page || $entity instanceof Block ? $entity : null;
        return [
            'entity_type' => $type,
            'entity_id' => $id,
            'title' => $entity?->getTitle(),
            'action' => $entity === null ? null : ($entity->isActive() ? 'publish' : 'unpublish'),
            'result' => $error === null ? 'success' : 'failure',
            'message' => $error,
        ];
    }

    /**
     * One line of the summary e-mail, such as: CMS page "Summer sale" (ID 12): published; rendered by the notifier.
     *
     * @param Phrase $type
     * @param Page|Block $entity
     * @return Phrase
     */
    private function describe(Phrase $type, Page|Block $entity): Phrase
    {
        return __(
            '%1 "%2" (ID %3): %4',
            $type,
            $entity->getTitle(),
            $entity->getId(),
            $entity->isActive() ? __('published') : __('unpublished')
        );
    }
}

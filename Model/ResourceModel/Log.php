<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * The log of the publish / unpublish dates applied: one row per page or block and run.
 */
class Log extends AbstractDb
{
    /**
     * Bind the resource to its table.
     */
    protected function _construct(): void
    {
        $this->_init('mageos_content_schedule_log', 'log_id');
    }

    /**
     * Insert the given rows in one query.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function add(array $rows): void
    {
        $this->_resources->getConnection()->insertMultiple($this->getMainTable(), $rows);
    }

    /**
     * Delete the rows logged before the given UTC date.
     *
     * @param string $before UTC "Y-m-d H:i:s"
     * @return int The number of rows deleted
     */
    public function purge(string $before): int
    {
        return $this->_resources->getConnection()->delete($this->getMainTable(), ['created_at < ?' => $before]);
    }
}

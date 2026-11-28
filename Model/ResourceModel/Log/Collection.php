<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model\ResourceModel\Log;

use MageOS\ContentSchedule\Model\ResourceModel\Log;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Rows of the content schedule log, for the admin grid.
 */
class Collection extends AbstractCollection
{
    /**
     * Rows are plain data objects: the log has no model of its own.
     */
    protected function _construct(): void
    {
        $this->_init(DataObject::class, Log::class);
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model\ResourceModel\Upcoming;

use Magento\Cms\Model\ResourceModel\Page;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * The dates still to apply: one row per date of a CMS page or block, so the grid filters, sorts and pages them in SQL.
 */
class Collection extends AbstractCollection
{
    /**
     * Rows are plain data objects; the CMS page resource only provides the connection and table names.
     */
    protected function _construct(): void
    {
        $this->_init(DataObject::class, Page::class);
        $this->_setIdFieldName('id');
    }

    /**
     * Select from the union of the four date columns, as "main_table".
     *
     * @return $this
     */
    protected function _initSelect()
    {
        $parts = [];
        foreach (['page' => 'cms_page', 'block' => 'cms_block'] as $type => $table) {
            foreach (['publish' => 'publish_at', 'unpublish' => 'unpublish_at'] as $action => $field) {
                $parts[] = $this->getConnection()->select()
                    ->from(
                        $this->getTable($table),
                        [
                            'id' => new \Zend_Db_Expr(sprintf("CONCAT('%s-%s-', %s_id)", $type, $action, $type)),
                            'entity_type' => new \Zend_Db_Expr(sprintf("'%s'", $type)),
                            'entity_id' => $type . '_id',
                            'action' => new \Zend_Db_Expr(sprintf("'%s'", $action)),
                            'scheduled_at' => $field,
                            'title',
                            'identifier',
                            'is_active',
                        ]
                    )
                    ->where($field . ' IS NOT NULL');
            }
        }
        $union = $this->getConnection()->select()->union($parts, Select::SQL_UNION_ALL);
        $this->getSelect()->from(['main_table' => $union]);
        return $this;
    }
}

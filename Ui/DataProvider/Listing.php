<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Ui\DataProvider;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data of the "Scheduled Content" and "Content Schedule Log" grids.
 *
 * Rows of CMS pages are listed only to roles allowed to manage pages, and rows of blocks to roles allowed to manage
 * blocks, as in the core CMS grids.
 */
class Listing extends AbstractDataProvider
{
    /**
     * @var bool
     */
    private bool $restricted = false;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param AbstractCollection $collection Rows with an "entity_type" column, "page" or "block"
     * @param AuthorizationInterface $authorization
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        AbstractCollection $collection,
        private readonly AuthorizationInterface $authorization,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collection;
    }

    /**
     * Leave out the rows of the CMS entities the admin user may not manage, then load.
     *
     * @return array<string,mixed>
     */
    public function getData()
    {
        if (!$this->restricted) {
            $this->restricted = true;
            foreach (['page' => 'Magento_Cms::page', 'block' => 'Magento_Cms::block'] as $type => $resource) {
                if (!$this->authorization->isAllowed($resource)) {
                    $this->collection->addFieldToFilter('entity_type', ['neq' => $type]);
                }
            }
        }
        return parent::getData();
    }

    /**
     * Sort by a column name only: the collection writes the field into the SQL as is.
     *
     * @param string $field
     * @param string $direction
     * @return void
     */
    public function addOrder($field, $direction)
    {
        if (preg_match('/^[a-z_]+$/', (string) $field)) {
            parent::addOrder($field, $direction);
        }
    }
}

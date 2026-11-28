<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Ui\DataProvider\Listing;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PHPUnit\Framework\TestCase;

/**
 * Tests which rows the "Scheduled Content" and log grids show, and how they sort.
 */
class ListingTest extends TestCase
{
    /**
     * @var array<int,array{0:string,1:mixed}>
     */
    private array $filters = [];

    /**
     * @var string[]
     */
    private array $orders = [];

    /**
     * A role without access to CMS blocks gets the page rows only.
     */
    public function testBlocksAreLeftOutWithoutBlockAccess(): void
    {
        $listing = $this->listing(['Magento_Cms::page']);

        $listing->getData();
        $listing->getData();

        $this->assertSame([['entity_type', ['neq' => 'block']]], $this->filters);
    }

    /**
     * A role allowed to manage pages and blocks gets every row.
     */
    public function testEveryRowIsListedWithFullAccess(): void
    {
        $this->listing(['Magento_Cms::page', 'Magento_Cms::block'])->getData();

        $this->assertSame([], $this->filters);
    }

    /**
     * Sorting takes a column name, never an SQL expression from the request.
     */
    public function testSortingIgnoresAnythingButAColumnName(): void
    {
        $listing = $this->listing([]);

        $listing->addOrder('scheduled_at', 'asc');
        $listing->addOrder('(SELECT 1)', 'asc');

        $this->assertSame(['scheduled_at'], $this->orders);
    }

    /**
     * A data provider over a recording collection, for a role allowed the given resources.
     *
     * @param string[] $allowed
     * @return Listing
     */
    private function listing(array $allowed): Listing
    {
        $collection = $this->createStub(AbstractCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function (string $field, mixed $condition) use ($collection): AbstractCollection {
                $this->filters[] = [$field, $condition];
                return $collection;
            }
        );
        $collection->method('addOrder')->willReturnCallback(
            function (string $field) use ($collection): AbstractCollection {
                $this->orders[] = $field;
                return $collection;
            }
        );
        $collection->method('toArray')->willReturn(['totalRecords' => 0, 'items' => []]);
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            fn (string $resource): bool => in_array($resource, $allowed, true)
        );
        return new Listing('listing_data_source', 'id', 'id', $collection, $authorization);
    }
}

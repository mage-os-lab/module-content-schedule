<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options of a grid column, given as value => label in di.xml (entity type, action, result, source).
 */
class Options implements OptionSourceInterface
{
    /**
     * @param array<string,string|\Stringable> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    /**
     * The options as the UI components expect them.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function toOptionArray(): array
    {
        $result = [];
        foreach ($this->options as $value => $label) {
            $result[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        return $result;
    }
}

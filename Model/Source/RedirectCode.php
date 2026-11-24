<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * HTTP status of the redirect of a disabled CMS page.
 */
class RedirectCode implements OptionSourceInterface
{
    /**
     * Permanent (301) or temporary (302).
     *
     * @return array<int,array{value:int,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 301, 'label' => __('Permanent (301)')],
            ['value' => 302, 'label' => __('Temporary (302)')],
        ];
    }
}

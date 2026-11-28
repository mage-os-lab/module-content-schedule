<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * "Edit" link of each row to the CMS page or block it is about.
 */
class EditLink extends Column
{
    private const ROUTES = ['page' => ['cms/page/edit', 'page_id'], 'block' => ['cms/block/edit', 'block_id']];

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $url
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $url,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Add the edit URL to each row, from its "entity_type" and "entity_id".
     *
     * @param array<string,mixed> $dataSource
     * @return array<string,mixed>
     */
    public function prepareDataSource(array $dataSource)
    {
        $name = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $i => $item) {
            [$route, $param] = self::ROUTES[$item['entity_type'] ?? ''] ?? [null, null];
            if ($route !== null) {
                $dataSource['data']['items'][$i][$name] = [
                    'edit' => [
                        'href' => $this->url->getUrl($route, [$param => $item['entity_id']]),
                        'label' => __('Edit'),
                    ],
                ];
            }
        }
        return $dataSource;
    }
}

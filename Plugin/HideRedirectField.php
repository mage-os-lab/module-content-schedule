<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Plugin;

use Magento\Cms\Model\Page\DataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Hides "Redirect when disabled" on the CMS page form while the redirect is turned off in the configuration.
 */
class HideRedirectField
{
    /**
     * @param ScopeConfigInterface $config
     */
    public function __construct(private readonly ScopeConfigInterface $config)
    {
    }

    /**
     * Mark the field invisible unless "Redirect Disabled Pages" is on.
     *
     * @param DataProvider $subject
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function afterGetMeta(DataProvider $subject, array $meta): array
    {
        if (!$this->config->isSetFlag(RedirectDisabledPage::CONFIG_PATH)) {
            $meta['general']['children']['redirect_to']['arguments']['data']['config']['visible'] = false;
        }
        return $meta;
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Cron;

use MageOS\ContentSchedule\Model\ResourceModel\Log;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Once a day, deletes the log rows older than the configured number of days.
 */
class CleanLog
{
    public const CONFIG_DAYS = 'cms/mageos_content_schedule/log_days';

    /**
     * @param ScopeConfigInterface $config
     * @param Log $log
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly Log $log
    ) {
    }

    /**
     * Purge the rows older than "Keep Log For" days; 0 or an empty value keeps the log forever.
     *
     * @return int The number of rows deleted
     */
    public function execute(): int
    {
        $days = (int) $this->config->getValue(self::CONFIG_DAYS);
        return $days > 0 ? $this->log->purge(gmdate('Y-m-d H:i:s', time() - $days * 86400)) : 0;
    }
}

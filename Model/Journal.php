<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model;

use MageOS\ContentSchedule\Model\ResourceModel\Log;
use Magento\Framework\FlagManager;

/**
 * Writes the log of the dates applied: who ran it, which page or block, when, and the result.
 */
class Journal
{
    public const SOURCE_CRON = 'cron';
    public const SOURCE_CLI = 'cli';
    private const FLAG = 'mageos_content_schedule_logged_failures';

    /**
     * @param Log $log
     * @param FlagManager $flags
     */
    public function __construct(
        private readonly Log $log,
        private readonly FlagManager $flags
    ) {
    }

    /**
     * Log the entries of one run; a failure already logged at the previous run is not logged again.
     *
     * A page or block that keeps failing is tried every minute: it is logged when it starts failing, and again
     * if it fails after having worked or stopped being due.
     *
     * @param array<string,array<string,mixed>> $entries Rows of Apply::entry(), by a key such as "page:12"
     * @param string $source self::SOURCE_CRON or self::SOURCE_CLI
     */
    public function record(array $entries, string $source): void
    {
        $known = array_values((array) $this->flags->getFlagData(self::FLAG));
        $failed = array_keys(array_filter($entries, fn (array $entry): bool => $entry['result'] === 'failure'));
        $now = gmdate('Y-m-d H:i:s');
        $rows = [];
        foreach ($entries as $key => $entry) {
            if ($entry['result'] !== 'failure' || !in_array($key, $known, true)) {
                $rows[] = ['created_at' => $now, 'source' => $source] + $entry;
            }
        }
        if ($rows) {
            $this->log->add($rows);
        }
        if ($failed !== $known) {
            $this->flags->saveFlag(self::FLAG, $failed);
        }
    }
}

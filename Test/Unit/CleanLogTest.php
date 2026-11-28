<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Cron\CleanLog;
use MageOS\ContentSchedule\Model\ResourceModel\Log;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the nightly purge of the content schedule log.
 */
class CleanLogTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $purgedBefore = [];

    /**
     * Rows older than the configured number of days are deleted.
     */
    public function testRowsOlderThanTheLifetimeArePurged(): void
    {
        $this->cleanLog('30')->execute();

        $this->assertCount(1, $this->purgedBefore);
        $expected = time() - 30 * 86400;
        $this->assertEqualsWithDelta($expected, strtotime($this->purgedBefore[0] . ' UTC'), 5);
    }

    /**
     * A lifetime of 0, or none, keeps the log forever.
     */
    public function testZeroKeepsTheLog(): void
    {
        $this->cleanLog('0')->execute();
        $this->cleanLog(null)->execute();

        $this->assertSame([], $this->purgedBefore);
    }

    /**
     * The cron job with the given "Keep Log For (Days)" value.
     *
     * @param string|null $days
     * @return CleanLog
     */
    private function cleanLog(?string $days): CleanLog
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($days);
        $log = $this->createStub(Log::class);
        $log->method('purge')->willReturnCallback(function (string $before): int {
            $this->purgedBefore[] = $before;
            return 0;
        });
        return new CleanLog($config, $log);
    }
}

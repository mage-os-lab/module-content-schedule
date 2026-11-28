<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Model\Journal;
use MageOS\ContentSchedule\Model\ResourceModel\Log;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests what the log of the applied dates records.
 */
class JournalTest extends TestCase
{
    /**
     * @var array<int,array<string,mixed>>
     */
    private array $rows = [];

    /**
     * @var array<string,mixed>
     */
    private array $flags = [];

    /**
     * A publication is logged with who ran it, the page and the result.
     */
    public function testAppliedDateIsLogged(): void
    {
        $this->journal()->record(['page:12' => $this->entry('success')], Journal::SOURCE_CLI);

        $this->assertCount(1, $this->rows);
        $this->assertSame('cli', $this->rows[0]['source']);
        $this->assertSame(12, $this->rows[0]['entity_id']);
        $this->assertSame('publish', $this->rows[0]['action']);
        $this->assertSame('success', $this->rows[0]['result']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $this->rows[0]['created_at']);
    }

    /**
     * A page that fails at every run is logged once, not every minute.
     */
    public function testRepeatedFailureIsLoggedOnce(): void
    {
        $journal = $this->journal();

        $journal->record(['page:12' => $this->entry('failure')], Journal::SOURCE_CRON);
        $journal->record(['page:12' => $this->entry('failure')], Journal::SOURCE_CRON);

        $this->assertCount(1, $this->rows);
        $this->assertSame('Could not save the page.', $this->rows[0]['message']);
    }

    /**
     * A page that fails again after a run without failing is logged again.
     */
    public function testFailureAfterARecoveryIsLoggedAgain(): void
    {
        $journal = $this->journal();

        $journal->record(['page:12' => $this->entry('failure')], Journal::SOURCE_CRON);
        $journal->record([], Journal::SOURCE_CRON);
        $journal->record(['page:12' => $this->entry('failure')], Journal::SOURCE_CRON);

        $this->assertCount(2, $this->rows);
    }

    /**
     * One entry of a run.
     *
     * @param string $result
     * @return array{entity_type:string,entity_id:int,title:?string,action:?string,result:string,message:?string}
     */
    private function entry(string $result): array
    {
        return [
            'entity_type' => 'page',
            'entity_id' => 12,
            'title' => 'Summer sale',
            'action' => 'publish',
            'result' => $result,
            'message' => $result === 'failure' ? 'Could not save the page.' : null,
        ];
    }

    /**
     * A journal writing to in-memory rows and flags.
     *
     * @return Journal
     */
    private function journal(): Journal
    {
        $log = $this->createStub(Log::class);
        $log->method('add')->willReturnCallback(function (array $rows): void {
            $this->rows = array_merge($this->rows, $rows);
        });
        $flags = $this->createStub(FlagManager::class);
        $flags->method('getFlagData')->willReturnCallback(fn (string $code): mixed => $this->flags[$code] ?? null);
        $flags->method('saveFlag')->willReturnCallback(function (string $code, mixed $value): bool {
            $this->flags[$code] = $value;
            return true;
        });
        return new Journal($log, $flags);
    }
}

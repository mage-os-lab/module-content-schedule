<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Console\Command\Run;
use MageOS\ContentSchedule\Cron\Apply;
use MageOS\ContentSchedule\Model\Journal;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\State;
use Magento\Framework\Config\ScopeInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests that the content-schedule:run command applies the dates in the same area as the cron job.
 */
class RunTest extends TestCase
{
    /**
     * The job runs in the crontab area set for real, not emulated: under an emulated area the design keeps the
     * emulated area instead of the e-mail template's, and the e-mails fail with "theme_dir was not passed".
     */
    public function testRunsInTheCrontabAreaAsTheCronJob(): void
    {
        $state = new State($this->createStub(ScopeInterface::class));
        // State looks the area codes up through the global object manager unless given its area list.
        $areas = $this->createStub(AreaList::class);
        $areas->method('getCodes')->willReturn([Area::AREA_ADMINHTML, Area::AREA_CRONTAB]);
        (new ReflectionProperty(State::class, 'areaList'))->setValue($state, $areas);
        $seen = [];
        $apply = $this->createStub(Apply::class);
        $apply->method('run')->willReturnCallback(function (string $source) use ($state, &$seen): int {
            $seen = [$source, $state->getAreaCode(), $state->isAreaCodeEmulated()];
            return 0;
        });

        $tester = new CommandTester(new Run($state, $apply));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame([Journal::SOURCE_CLI, Area::AREA_CRONTAB, false], $seen);
    }
}

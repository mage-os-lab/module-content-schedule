<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Model\Notifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests that a page or block that keeps failing is e-mailed once, not at every cron run.
 */
class NotifierTest extends TestCase
{
    /** @var string[] Failure keys remembered as already e-mailed */
    private array $flag = [];

    /** @var array<int,array<string,mixed>> Template variables of each e-mail sent */
    private array $sent = [];

    /**
     * The same failure at the next run sends nothing; a new failure is e-mailed alone.
     */
    public function testFailureIsEmailedOnce(): void
    {
        $notifier = $this->notifier();

        $notifier->notify([], ['page:1' => 'CMS page 1: broken']);
        $notifier->notify([], ['page:1' => 'CMS page 1: broken']);
        $notifier->notify([], ['page:1' => 'CMS page 1: broken', 'block:2' => 'CMS block 2: broken']);

        $this->assertCount(2, $this->sent);
        $this->assertSame([['text' => 'CMS page 1: broken']], $this->sent[0]['failures']);
        $this->assertSame([['text' => 'CMS block 2: broken']], $this->sent[1]['failures']);
        $this->assertSame(1, $this->sent[1]['count']);
    }

    /**
     * A failure that stopped, then comes back, is e-mailed again.
     */
    public function testFailureThatComesBackIsEmailedAgain(): void
    {
        $notifier = $this->notifier();

        $notifier->notify([], ['page:1' => 'CMS page 1: broken']);
        $notifier->notify([], []);
        $notifier->notify([], ['page:1' => 'CMS page 1: broken']);

        $this->assertCount(2, $this->sent);
    }

    /**
     * While notifying is off nothing is remembered, so the failure is e-mailed once notifying is turned on.
     */
    public function testFailureSeenWhileNotifyingIsOffIsEmailedLater(): void
    {
        $this->notifier(false)->notify([], ['page:1' => 'CMS page 1: broken']);
        $this->assertSame([], $this->flag);

        $this->notifier()->notify([], ['page:1' => 'CMS page 1: broken']);

        $this->assertCount(1, $this->sent);
        $this->assertSame(['page:1'], $this->flag);
    }

    /**
     * The daily reminder is not sent when it is turned off, nor when there is no date to list.
     */
    public function testNoReminderWhenOffOrEmpty(): void
    {
        $this->notifier(true, false)->remind(['Dec 1 — Publish: CMS page "Sale" (ID 1)']);
        $this->notifier()->remind([]);

        $this->assertSame([], $this->sent);
    }

    /**
     * The daily reminder lists its lines, HTML-escaped.
     */
    public function testReminderListsItsLinesEscaped(): void
    {
        $this->notifier()->remind(['Dec 1 — Publish: CMS page "<Sale>" (ID 1)']);

        $this->assertSame(
            [['count' => 1, 'lines' => [['text' => 'Dec 1 — Publish: CMS page &quot;&lt;Sale&gt;&quot; (ID 1)']]]],
            $this->sent
        );
    }

    /**
     * A notifier whose flag storage and sent e-mails are this test's arrays.
     *
     * @param bool $notify "E-mail a Summary"
     * @param bool $remind "E-mail a Daily Reminder"
     * @return Notifier
     */
    private function notifier(bool $notify = true, bool $remind = true): Notifier
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('editor@example.com');
        $config->method('isSetFlag')->willReturnCallback(
            fn (string $path): bool => str_ends_with($path, '/remind') ? $remind : $notify
        );
        $builder = $this->createStub(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFromByScope', 'addTo'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('setTemplateVars')->willReturnCallback(function (array $vars) use ($builder) {
            $this->sent[] = $vars;
            return $builder;
        });
        $builder->method('getTransport')->willReturn($this->createStub(TransportInterface::class));
        $flags = $this->createStub(FlagManager::class);
        $flags->method('getFlagData')->willReturnCallback(fn (): array => $this->flag);
        $flags->method('saveFlag')->willReturnCallback(function (string $code, array $value): bool {
            $this->flag = $value;
            return true;
        });
        return new Notifier($config, $builder, new Escaper(), $this->createStub(LoggerInterface::class), $flags);
    }
}

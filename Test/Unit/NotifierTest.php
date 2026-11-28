<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Model\Notifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\MailException;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests that a failure is e-mailed once, not at every cron run, and that the daily reminder is sent once a day.
 */
class NotifierTest extends TestCase
{
    /** @var array<string,mixed> Flags saved, by code */
    private array $flags = [];

    /** @var int Number of sends that fail with a transport error before the transport works again */
    private int $transportErrors = 0;

    /** @var int E-mails actually delivered by the transport */
    private int $delivered = 0;

    /** @var array<int,array<string,mixed>> Template variables of each e-mail sent */
    private array $sent = [];

    /** @var bool Whether the store environment of the e-mail is emulated */
    private bool $emulating = false;

    /**
     * Restore the phrase renderer that the emulation stub replaces.
     */
    protected function tearDown(): void
    {
        Phrase::setRenderer(new Placeholder());
    }

    /**
     * A summary that could not be sent is added to the next one, which is sent once.
     */
    public function testUnsentSummaryIsAddedToTheNextOne(): void
    {
        $notifier = $this->notifier();
        $this->transportErrors = 1;

        $notifier->notify(['CMS page "A" (ID 1): published'], []);
        $notifier->notify(['CMS page "B" (ID 2): published'], []);
        $notifier->notify([], []);

        $this->assertSame(1, $this->delivered);
        $this->assertSame(2, $this->sent[1]['count']);
        $this->assertSame(
            [
                ['text' => 'CMS page &quot;A&quot; (ID 1): published'],
                ['text' => 'CMS page &quot;B&quot; (ID 2): published'],
            ],
            $this->sent[1]['changes']
        );
        $this->assertCount(2, $this->sent);
    }

    /**
     * While notifying is off the changes are not kept for later.
     */
    public function testChangesSeenWhileNotifyingIsOffAreNotKept(): void
    {
        $this->notifier(false)->notify(['CMS page "A" (ID 1): published'], []);
        $this->notifier()->notify(['CMS page "B" (ID 2): published'], []);

        $this->assertSame([['text' => 'CMS page &quot;B&quot; (ID 2): published']], $this->sent[0]['changes']);
    }

    /**
     * The lines are rendered in the language of the store view that sends the e-mail, before it is sent.
     */
    public function testLinesAreRenderedInTheLanguageOfTheEmail(): void
    {
        $this->notifier()->notify(
            [__('%1 "%2" (ID %3): %4', __('CMS page'), 'Sale', 1, __('published'))],
            ['block:2' => __('%1 %2: %3', __('CMS block'), 2, 'broken')]
        );

        $this->assertSame([['text' => 'Page CMS &quot;Sale&quot; (ID 1): publiée']], $this->sent[0]['changes']);
        $this->assertSame([['text' => 'Bloc CMS 2: broken']], $this->sent[0]['failures']);
    }

    /**
     * The subject counts changes and failures apart: an e-mail of failures only does not announce "changes".
     */
    public function testSubjectCountsChangesAndFailuresApart(): void
    {
        $notifier = $this->notifier();

        $notifier->notify(['CMS page "A" (ID 1): published'], []);
        $notifier->notify([], ['page:1' => 'CMS page 1: broken']);
        $notifier->notify(['CMS page "B" (ID 2): published'], ['block:2' => 'CMS block 2: broken']);

        $this->assertSame(
            [
                '1 changement(s)',
                '1 page(s) ou bloc(s) non enregistré(s)',
                '1 changement(s), 1 page(s) ou bloc(s) non enregistré(s)',
            ],
            array_column($this->sent, 'summary')
        );
    }

    /**
     * A Magento error kept as a phrase is rendered in the language of the e-mail, as the rest of the line.
     */
    public function testMagentoErrorIsInTheLanguageOfTheEmail(): void
    {
        $this->notifier()->notify(
            [],
            ['page:1' => __('%1 %2: %3', __('CMS page'), 1, new Phrase('The URL key is wrong.'))]
        );

        $this->assertSame([['text' => 'Page CMS 1: La clé d&#039;URL est fausse.']], $this->sent[0]['failures']);
    }

    /**
     * A reminder that keeps failing is tried every hour, night included, until sent; the next one leaves at 7:00.
     */
    public function testMissedReminderIsRetriedAtNightUntilSent(): void
    {
        $notifier = $this->notifier();
        $this->transportErrors = 3;

        $delivered = [];
        $runs = ['2026-12-01 07:00', '2026-12-01 23:00', '2026-12-02 00:00', '2026-12-02 01:00', '2026-12-02 06:00',
            '2026-12-02 07:00'];
        foreach ($runs as $now) {
            $notifier->remind(fn (): array => ['Publish: CMS page "Sale" (ID 1)'], $now);
            $delivered[] = $this->delivered;
        }

        $this->assertSame([0, 0, 0, 1, 1, 2], $delivered);
    }

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
        $this->assertArrayNotHasKey('mageos_content_schedule_failures', $this->flags);

        $this->notifier()->notify([], ['page:1' => 'CMS page 1: broken']);

        $this->assertCount(1, $this->sent);
        $this->assertSame(['page:1'], $this->flags['mageos_content_schedule_failures']);
    }

    /**
     * The daily reminder is not sent when it is turned off, nor when there is no date to list.
     */
    public function testNoReminderWhenOffOrEmpty(): void
    {
        $this->notifier(true, false)->remind(
            fn (): array => ['Dec 1 — Publish: CMS page "Sale" (ID 1)'],
            '2026-12-01 07:00'
        );
        $this->notifier()->remind(fn (): array => [], '2026-12-01 07:00');

        $this->assertSame([], $this->sent);
    }

    /**
     * The daily reminder lists its lines, HTML-escaped.
     */
    public function testReminderListsItsLinesEscaped(): void
    {
        $this->notifier()->remind(fn (): array => ['Dec 1 — Publish: CMS page "<Sale>" (ID 1)'], '2026-12-01 07:00');

        $this->assertSame(
            [['count' => 1, 'lines' => [['text' => 'Dec 1 — Publish: CMS page &quot;&lt;Sale&gt;&quot; (ID 1)']]]],
            $this->sent
        );
    }

    /**
     * The reminder of a day is sent once, whatever the number of cron runs that day; the next day it is sent again.
     */
    public function testReminderIsSentOnceADay(): void
    {
        $notifier = $this->notifier();

        $notifier->remind(fn (): array => ['Dec 1 — Publish: CMS page "Sale" (ID 1)'], '2026-12-01 07:00');
        $notifier->remind(fn (): array => ['Dec 1 — Publish: CMS page "Sale" (ID 1)'], '2026-12-01 07:00');
        $this->assertSame(1, $this->delivered);

        $notifier->remind(fn (): array => ['Dec 2 — Unpublish: CMS page "Sale" (ID 1)'], '2026-12-02 07:00');
        $this->assertSame(2, $this->delivered);
    }

    /**
     * A reminder that the transport failed to send is sent at the next cron run, and only once.
     */
    public function testFailedReminderIsSentAtTheNextRun(): void
    {
        $notifier = $this->notifier();
        $this->transportErrors = 1;

        $notifier->remind(fn (): array => ['Dec 1 — Publish: CMS page "Sale" (ID 1)'], '2026-12-01 07:00');
        $this->assertSame(0, $this->delivered);

        $notifier->remind(fn (): array => ['Dec 1 — Publish: CMS page "Sale" (ID 1)'], '2026-12-01 07:00');
        $notifier->remind(fn (): array => ['Dec 1 — Publish: CMS page "Sale" (ID 1)'], '2026-12-01 07:00');
        $this->assertSame(1, $this->delivered);
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
        $transport = $this->createStub(TransportInterface::class);
        $transport->method('sendMessage')->willReturnCallback(function (): void {
            $this->assertFalse($this->emulating, 'The emulation is stopped before the e-mail renders its own.');
            if ($this->transportErrors-- > 0) {
                throw new MailException(new Phrase('Transport error'));
            }
            $this->delivered++;
        });
        $builder->method('getTransport')->willReturn($transport);
        $flags = $this->createStub(FlagManager::class);
        $flags->method('getFlagData')->willReturnCallback(fn (string $code): mixed => $this->flags[$code] ?? null);
        $flags->method('saveFlag')->willReturnCallback(function (string $code, mixed $value): bool {
            $this->flags[$code] = $value;
            return true;
        });
        $emulation = $this->createStub(Emulation::class);
        $emulation->method('startEnvironmentEmulation')->willReturnCallback(function (): void {
            $this->emulating = true;
            Phrase::setRenderer(new class implements RendererInterface {
                /**
                 * Translate into French, then replace the placeholders.
                 *
                 * @param string[] $source
                 * @param array<int|string,mixed> $arguments
                 * @return string
                 */
                public function render(array $source, array $arguments): string
                {
                    $fr = [
                        'CMS page' => 'Page CMS',
                        'CMS block' => 'Bloc CMS',
                        'published' => 'publiée',
                        'The URL key is wrong.' => 'La clé d\'URL est fausse.',
                        '%1 change(s)' => '%1 changement(s)',
                        '%1 page(s) or block(s) not saved' => '%1 page(s) ou bloc(s) non enregistré(s)',
                        '%1 change(s), %2 page(s) or block(s) not saved'
                            => '%1 changement(s), %2 page(s) ou bloc(s) non enregistré(s)',
                    ];
                    return (new Placeholder())->render([$fr[end($source)] ?? end($source)], $arguments);
                }
            });
        });
        $emulation->method('stopEnvironmentEmulation')->willReturnCallback(function (): Emulation {
            $this->emulating = false;
            Phrase::setRenderer(new Placeholder());
            return $this->createStub(Emulation::class);
        });
        return new Notifier(
            $config,
            $builder,
            new Escaper(),
            $this->createStub(LoggerInterface::class),
            $flags,
            $emulation
        );
    }
}

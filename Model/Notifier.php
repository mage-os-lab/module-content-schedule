<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model;

use DateTimeImmutable;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Phrase;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * E-mails the configured addresses: the summary of a cron run, and the daily reminder of the dates to come.
 */
class Notifier
{
    private const CONFIG = 'cms/mageos_content_schedule/';
    private const FLAG = 'mageos_content_schedule_failures';
    private const REMINDED = 'mageos_content_schedule_reminded';
    private const UNSENT = 'mageos_content_schedule_unsent';
    // ponytail: the oldest unsent changes are dropped past this; they stay in the Content Schedule Log
    private const MAX_UNSENT = 100;

    /**
     * @param ScopeConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     * @param FlagManager $flags
     * @param Emulation $emulation
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger,
        private readonly FlagManager $flags,
        private readonly Emulation $emulation
    ) {
    }

    /**
     * Send one e-mail listing the changes and the failures not e-mailed yet; nothing is sent when there are none.
     *
     * A page or block that keeps failing is tried at every cron run but e-mailed once: the failures already
     * e-mailed are remembered until they stop failing. One that was not e-mailed (notifying off, mail error)
     * is not remembered, so it is reported as soon as an e-mail goes out.
     *
     * Changes whose e-mail could not be sent (mail error) are kept, up to MAX_UNSENT, and added to the next e-mail;
     * while notifying is off they are not kept.
     *
     * @param array<int,string|Phrase> $changes
     * @param array<string,string|Phrase> $failures The text of each failure, by a key such as "page:12"
     */
    public function notify(array $changes, array $failures): void
    {
        $known = array_values((array) $this->flags->getFlagData(self::FLAG));
        $new = array_diff_key($failures, array_flip($known));
        $unsent = array_values((array) $this->flags->getFlagData(self::UNSENT));
        $sent = false;
        if ($changes || $new || $unsent) {
            $changes = array_merge($unsent, $changes);
            [$changes, $new, $summary] = $this->inLanguage(fn (): array => [
                array_map('strval', $changes),
                array_map('strval', $new),
                (string) $this->summary(count($changes), count($new)),
            ]);
            $sent = $this->send('notify_template', [
                'summary' => $summary,
                'count' => count($changes) + count($new),
                'changes' => $this->lines($changes),
                'failures' => $this->lines($new),
                'has_changes' => $changes ? '1' : '',
                'has_failures' => $new ? '1' : '',
            ]);
            $left = $sent || !$this->recipients() ? [] : array_slice($changes, -self::MAX_UNSENT);
            if ($left !== $unsent) {
                $this->flags->saveFlag(self::UNSENT, $left);
            }
        }
        $reported = array_keys($sent ? $failures : array_intersect_key($failures, array_flip($known)));
        if ($reported !== $known) {
            $this->flags->saveFlag(self::FLAG, $reported);
        }
    }

    /**
     * What the e-mail holds, for its subject: changes and failures counted apart.
     *
     * @param int $changes
     * @param int $failures
     * @return Phrase
     */
    private function summary(int $changes, int $failures): Phrase
    {
        if (!$failures) {
            return __('%1 change(s)', $changes);
        }
        return $changes
            ? __('%1 change(s), %2 page(s) or block(s) not saved', $changes, $failures)
            : __('%1 page(s) or block(s) not saved', $failures);
    }

    /**
     * Send the reminder of the day listing the dates to come; nothing is sent when there are none or reminding is off.
     *
     * The reminder of a day is due from 7:00 to 7:00 the next day. It is marked done once the e-mail is sent, or
     * when there is nothing to list: a reminder that was not sent (notifying off, mail error) is tried again at the
     * next hourly cron run, night included, and one sent is not sent again.
     *
     * @param callable():array<int,string|Phrase> $lines The lines, built in the language of the e-mail
     * @param string $now The local date and time of the run, such as "2026-12-01 07:00"
     */
    public function remind(callable $lines, string $now): void
    {
        $day = (new DateTimeImmutable($now))->modify('-7 hours')->format('Y-m-d');
        if (!$this->config->isSetFlag(self::CONFIG . 'remind') || $this->flags->getFlagData(self::REMINDED) === $day) {
            return;
        }
        $lines = $this->inLanguage(fn (): array => array_map('strval', $lines()));
        if (!$lines || $this->send('remind_template', ['count' => count($lines), 'lines' => $this->lines($lines)])) {
            $this->flags->saveFlag(self::REMINDED, $day);
        }
    }

    /**
     * Send the template of the given config field to the configured addresses, if notifying is on.
     *
     * A mail failure is logged, not thrown: the changes are already saved.
     *
     * @param string $template
     * @param array<string,mixed> $vars
     * @return bool whether the e-mail was sent
     */
    private function send(string $template, array $vars): bool
    {
        $to = $this->recipients();
        if (!$to) {
            return false;
        }
        try {
            $this->transportBuilder
                ->setTemplateIdentifier((string) $this->config->getValue(self::CONFIG . $template))
                ->setTemplateOptions(['area' => Area::AREA_ADMINHTML, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars($vars)
                ->setFromByScope((string) $this->config->getValue(self::CONFIG . 'notify_identity'))
                ->addTo($to)
                ->getTransport()
                ->sendMessage();
            return true;
        } catch (Exception $e) {
            $this->logger->error('Content schedule: e-mail not sent: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * The configured addresses, none when notifying is off.
     *
     * @return string[]
     */
    private function recipients(): array
    {
        if (!$this->config->isSetFlag(self::CONFIG . 'notify')) {
            return [];
        }
        $to = (string) $this->config->getValue(self::CONFIG . 'notify_to');
        return array_values(array_filter(array_map('trim', explode(',', $to))));
    }

    /**
     * Run the callback in the store environment of the e-mail, so that phrases and dates are in its language.
     *
     * The emulation is stopped before sending: the e-mail template starts its own, and nesting is not allowed.
     *
     * @param callable():array<mixed> $callback
     * @return array<mixed>
     */
    private function inLanguage(callable $callback): array
    {
        $this->emulation->startEnvironmentEmulation(Store::DEFAULT_STORE_ID, Area::AREA_ADMINHTML, true);
        try {
            return $callback();
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * The template's list items: each text HTML-escaped.
     *
     * @param string[] $texts
     * @return array<int,array{text:string}>
     */
    private function lines(array $texts): array
    {
        return array_values(array_map(
            fn (string $text): array => ['text' => $this->escaper->escapeHtml($text)],
            $texts
        ));
    }
}

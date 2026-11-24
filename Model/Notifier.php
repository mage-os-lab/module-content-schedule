<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * E-mails the configured addresses: the summary of a cron run, and the daily reminder of the dates to come.
 */
class Notifier
{
    private const CONFIG = 'cms/mageos_content_schedule/';
    private const FLAG = 'mageos_content_schedule_failures';

    /**
     * @param ScopeConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     * @param FlagManager $flags
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger,
        private readonly FlagManager $flags
    ) {
    }

    /**
     * Send one e-mail listing the changes and the failures not e-mailed yet; nothing is sent when both are empty.
     *
     * A page or block that keeps failing is tried at every cron run but e-mailed once: the failures already
     * e-mailed are remembered until they stop failing. One that was not e-mailed (notifying off, mail error)
     * is not remembered, so it is reported as soon as an e-mail goes out.
     *
     * @param string[] $changes
     * @param array<string,string> $failures The text of each failure, by a key such as "page:12"
     */
    public function notify(array $changes, array $failures): void
    {
        $known = array_values((array) $this->flags->getFlagData(self::FLAG));
        $new = array_diff_key($failures, array_flip($known));
        $sent = ($changes || $new) && $this->send('notify_template', [
            'count' => count($changes) + count($new),
            'changes' => $this->lines($changes),
            'failures' => $this->lines($new),
            'has_changes' => $changes ? '1' : '',
            'has_failures' => $new ? '1' : '',
        ]);
        $reported = array_keys($sent ? $failures : array_intersect_key($failures, array_flip($known)));
        if ($reported !== $known) {
            $this->flags->saveFlag(self::FLAG, $reported);
        }
    }

    /**
     * Send the daily reminder listing the dates to come; nothing is sent when there are none or reminding is off.
     *
     * @param string[] $lines
     */
    public function remind(array $lines): void
    {
        if ($lines && $this->config->isSetFlag(self::CONFIG . 'remind')) {
            $this->send('remind_template', ['count' => count($lines), 'lines' => $this->lines($lines)]);
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
        $to = (string) $this->config->getValue(self::CONFIG . 'notify_to');
        $to = array_values(array_filter(array_map('trim', explode(',', $to))));
        if (!$to || !$this->config->isSetFlag(self::CONFIG . 'notify')) {
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

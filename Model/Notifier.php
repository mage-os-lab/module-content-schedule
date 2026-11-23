<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Model;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * E-mails the summary of a cron run (pages and blocks published, unpublished or not saved) to the configured addresses.
 */
class Notifier
{
    private const CONFIG = 'cms/mageos_content_schedule/';

    /**
     * @param ScopeConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send one e-mail listing the changes and failures; nothing is sent when both are empty or notifying is off.
     *
     * A mail failure is logged, not thrown: the changes are already saved.
     *
     * @param string[] $changes
     * @param string[] $failures
     */
    public function notify(array $changes, array $failures): void
    {
        $to = (string) $this->config->getValue(self::CONFIG . 'notify_to');
        $to = array_values(array_filter(array_map('trim', explode(',', $to))));
        if ((!$changes && !$failures) || !$to || !$this->config->isSetFlag(self::CONFIG . 'notify')) {
            return;
        }
        $lines = fn (array $texts): array => array_map(
            fn (string $text): array => ['text' => $this->escaper->escapeHtml($text)],
            $texts
        );
        try {
            $this->transportBuilder
                ->setTemplateIdentifier((string) $this->config->getValue(self::CONFIG . 'notify_template'))
                ->setTemplateOptions(['area' => Area::AREA_ADMINHTML, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars([
                    'count' => count($changes) + count($failures),
                    'changes' => $lines($changes),
                    'failures' => $lines($failures),
                    'has_changes' => $changes ? '1' : '',
                    'has_failures' => $failures ? '1' : '',
                ])
                ->setFromByScope((string) $this->config->getValue(self::CONFIG . 'notify_identity'))
                ->addTo($to)
                ->getTransport()
                ->sendMessage();
        } catch (Exception $e) {
            $this->logger->error('Content schedule: summary e-mail not sent: ' . $e->getMessage());
        }
    }
}

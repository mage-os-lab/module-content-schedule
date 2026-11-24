<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Refuses to save a CMS page whose "Redirect when disabled" is neither a store path nor an http(s) URL, or is itself.
 */
class ValidateRedirect implements ObserverInterface
{
    /**
     * Trim the target; reject whitespace, control characters (they would break the Location header) and other schemes.
     *
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $page = $observer->getData('object');
        $target = trim((string) $page->getData('redirect_to'));
        if ($target !== '' && !preg_match('#^(https?://[^\s\x00-\x1f\x7f]+|[^\s\x00-\x1f\x7f:]+)$#i', $target)) {
            throw new LocalizedException(
                __('"Redirect when disabled" must be a path such as summer-sale or a URL starting with https://.')
            );
        }
        if ($target !== '' && strcasecmp(trim($target, '/'), trim((string) $page->getData('identifier'), '/')) === 0) {
            throw new LocalizedException(__('"Redirect when disabled" cannot be the page itself.'));
        }
        $page->setData('redirect_to', $target === '' ? null : $target);
    }
}

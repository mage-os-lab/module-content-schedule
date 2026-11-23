<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Plugin;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Controller\Page\View;
use Magento\Cms\Model\Page;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Answers a disabled CMS page that has a "Redirect when disabled" target with a 301 instead of the 404 page.
 *
 * The page's URL rewrite still leads to the page view, which forwards to "noroute" because the page is disabled;
 * only that case costs a page load, active pages are untouched.
 */
class RedirectDisabledPage
{
    public const CONFIG_PATH = 'cms/mageos_content_schedule/redirect';

    /**
     * @param ScopeConfigInterface $config
     * @param PageRepositoryInterface $pageRepository
     * @param RedirectFactory $redirectFactory
     * @param UrlInterface $url
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $url,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Replace the "noroute" forward of a disabled page of the current store (or all stores) with its redirect.
     *
     * A target starting with http:// or https:// is used as is; anything else is a path of the current store.
     * forward() has already queued the "noroute" dispatch on the request, so the request is marked dispatched.
     *
     * @param View $subject
     * @param ResultInterface $result
     * @return ResultInterface
     */
    public function afterExecute(View $subject, ResultInterface $result): ResultInterface
    {
        $request = $subject->getRequest();
        $id = $request->getParam('page_id') ?? $request->getParam('id');
        if (!$result instanceof Forward || !$request instanceof Http || !$id
            || !$this->config->isSetFlag(self::CONFIG_PATH)
        ) {
            return $result;
        }
        try {
            $page = $this->pageRepository->getById((int) $id);
        } catch (NoSuchEntityException) {
            return $result;
        }
        if (!$page instanceof Page) {
            return $result;
        }
        $target = trim((string) $page->getData('redirect_to'));
        $stores = array_map('intval', (array) $page->getData('store_id'));
        $inStore = array_intersect($stores, [0, (int) $this->storeManager->getStore()->getId()]);
        if ($target === '' || $page->isActive() || !$inStore) {
            return $result;
        }
        $url = preg_match('#^https?://#i', $target)
            ? $target
            : $this->url->getUrl('', ['_direct' => ltrim($target, '/')]);
        $request->setDispatched(true);
        /** @var Redirect $redirect */
        $redirect = $this->redirectFactory->create();
        return $redirect->setUrl($url)->setHttpResponseCode(301);
    }
}

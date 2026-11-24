<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Plugin\RedirectDisabledPage;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Controller\Page\View;
use Magento\Cms\Model\Page;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the status of the redirect of a disabled CMS page, and that a page never redirects to itself.
 */
class RedirectDisabledPageTest extends TestCase
{
    /** @var int|null Status given to the redirect result */
    private ?int $status = null;

    /**
     * The redirect is a 301 unless the configuration asks for a 302.
     *
     * @param string|null $configured
     * @param int $expected
     */
    #[DataProvider('codes')]
    public function testRedirectUsesTheConfiguredStatus(?string $configured, int $expected): void
    {
        $result = $this->redirect('summer-sale', '/old-page', $configured);

        $this->assertInstanceOf(Redirect::class, $result);
        $this->assertSame($expected, $this->status);
    }

    /**
     * A target that is the requested page keeps the 404 instead of looping, as a path or as a full URL.
     *
     * @param string $target
     */
    #[DataProvider('ownTargets')]
    public function testTargetThatIsTheRequestedPageIsNotFollowed(string $target): void
    {
        $result = $this->redirect($target, '/Summer-Sale/', '302');

        $this->assertInstanceOf(Forward::class, $result);
    }

    /**
     * Targets that lead back to the page requested as /Summer-Sale/?utm=1.
     *
     * @return array<string,array{string}>
     */
    public static function ownTargets(): array
    {
        return ['path' => ['summer-sale'], 'url' => ['https://shop.test/summer-sale?from=old']];
    }

    /**
     * Configured values and the status sent.
     *
     * @return array<string,array{string|null,int}>
     */
    public static function codes(): array
    {
        return [
            'unset' => [null, 301],
            'permanent' => ['301', 301],
            'temporary' => ['302', 302],
            'other' => ['307', 301],
        ];
    }

    /**
     * Run the plugin on the "noroute" forward of a disabled page of the current store.
     *
     * @param string $target The page's "Redirect when disabled"
     * @param string $requestedPath Path asked by the visitor, before the URL rewrite
     * @param string|null $code The configured redirect type
     * @return object
     */
    private function redirect(string $target, string $requestedPath, ?string $code): object
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturn(true);
        $config->method('getValue')->willReturn($code);
        $page = $this->createStub(Page::class);
        $page->method('isActive')->willReturn(false);
        $page->method('getData')->willReturnCallback(
            fn (string $key): mixed => ['redirect_to' => $target, 'store_id' => [0]][$key] ?? null
        );
        $pages = $this->createStub(PageRepositoryInterface::class);
        $pages->method('getById')->willReturn($page);
        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setUrl')->willReturnSelf();
        $redirect->method('setHttpResponseCode')->willReturnCallback(function (int $status) use ($redirect) {
            $this->status = $status;
            return $redirect;
        });
        $factory = $this->createStub(RedirectFactory::class);
        $factory->method('create')->willReturn($redirect);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn (string $route, array $params): string => 'https://shop.test/' . $params['_direct']
        );
        $url->method('getCurrentUrl')->willReturn('https://shop.test' . $requestedPath . '?utm=1');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($this->createStub(StoreInterface::class));
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturn('5');
        $request->method('getOriginalPathInfo')->willReturn($requestedPath);
        $view = $this->createStub(View::class);
        $view->method('getRequest')->willReturn($request);

        return (new RedirectDisabledPage($config, $pages, $factory, $url, $stores))
            ->afterExecute($view, $this->createStub(Forward::class));
    }
}

<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Test\Unit;

use MageOS\ContentSchedule\Observer\ValidateRedirect;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the validation of "Redirect when disabled" before a CMS page is saved.
 */
class ValidateRedirectTest extends TestCase
{
    /**
     * Store paths and http(s) URLs are kept, trimmed; an empty value is stored as null.
     *
     * @param string $value
     * @param string|null $stored
     */
    #[DataProvider('validTargets')]
    public function testValidTargetIsKept(string $value, ?string $stored): void
    {
        $page = new DataObject(['redirect_to' => $value]);

        (new ValidateRedirect())->execute(new Observer(['object' => $page]));

        $this->assertSame($stored, $page->getData('redirect_to'));
    }

    /**
     * Line breaks (header injection), spaces and other schemes are refused.
     *
     * @param string $value
     */
    #[DataProvider('invalidTargets')]
    public function testInvalidTargetIsRefused(string $value): void
    {
        $this->expectException(LocalizedException::class);

        (new ValidateRedirect())->execute(new Observer(['object' => new DataObject(['redirect_to' => $value])]));
    }

    /**
     * A page that redirects to its own URL key would loop: refused, whatever the case or slashes.
     */
    public function testRedirectToThePageItselfIsRefused(): void
    {
        $page = new DataObject(['identifier' => 'summer-sale', 'redirect_to' => '/Summer-Sale/']);

        $this->expectException(LocalizedException::class);

        (new ValidateRedirect())->execute(new Observer(['object' => $page]));
    }

    /**
     * Valid inputs and what is stored.
     *
     * @return array<string,array{string,string|null}>
     */
    public static function validTargets(): array
    {
        return [
            'path' => [' summer-sale ', 'summer-sale'],
            'nested path' => ['/promo/summer.html', '/promo/summer.html'],
            'url' => ['https://example.com/sale?x=1', 'https://example.com/sale?x=1'],
            'empty' => ['  ', null],
        ];
    }

    /**
     * Refused inputs.
     *
     * @return array<string,array{string}>
     */
    public static function invalidTargets(): array
    {
        return [
            'line break' => ["sale\r\nSet-Cookie: x=1"],
            'space' => ['summer sale'],
            'javascript' => ['javascript:alert(1)'],
            'ftp' => ['ftp://example.com'],
        ];
    }
}

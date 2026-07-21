<?php
/**
 * Magendoo ProductLabels - Config unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Test\Unit\Model;

use Magendoo\ProductLabels\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface|MockObject $scopeConfig;

    /**
     * @var Config
     */
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReadsStoreScopedFlag(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, 5)
            ->willReturn(true);
        $this->assertTrue($this->config->isEnabled(5));
    }

    #[DataProvider('maxLabelsProvider')]
    public function testGetMaxLabelsPerProductClampsToZeroFloor(mixed $stored, int $expected): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_MAX_LABELS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($stored);
        $this->assertSame($expected, $this->config->getMaxLabelsPerProduct());
    }

    public static function maxLabelsProvider(): array
    {
        return [
            'normal' => ['2', 2],
            'zero_means_unlimited' => ['0', 0],
            'negative_clamped' => ['-3', 0],
            'null_config' => [null, 0],
            'garbage' => ['abc', 0],
        ];
    }
}

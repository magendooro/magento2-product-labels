<?php
/**
 * Magendoo ProductLabels - LabelResolver unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Test\Unit\Model;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Model\Config;
use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\LabelResolver;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the merge/filter/priority/cap logic with the request caches
 * seeded via reflection; the DB-facing loaders are integration-tested through
 * the storefront/GraphQL smoke tests.
 */
#[CoversClass(LabelResolver::class)]
class LabelResolverTest extends TestCase
{
    private const STORE_ID = 1;
    private const PRODUCT_ID = 100;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $config;

    /**
     * @var LabelResolver
     */
    private LabelResolver $resolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->resolver = new LabelResolver(
            $this->createMock(ResourceConnection::class),
            $this->getMockBuilder(CollectionFactory::class)
                ->disableOriginalConstructor()->onlyMethods(['create'])->getMock(),
            $this->createMock(EavConfig::class),
            $this->config
        );
    }

    /**
     * @param array<int, Label> $activeLabels
     * @param int[] $assignedLabelIds
     */
    private function seed(array $activeLabels, array $assignedLabelIds): void
    {
        $ref = new \ReflectionObject($this->resolver);
        $prop = $ref->getProperty('activeLabels');
        $prop->setValue($this->resolver, [self::STORE_ID => $activeLabels]);
        $prop = $ref->getProperty('assignments');
        $prop->setValue($this->resolver, [self::STORE_ID => [self::PRODUCT_ID => $assignedLabelIds]]);
    }

    private function makeLabel(int $id, int $priority, bool $plp = true, bool $pdp = true): Label
    {
        $label = (new ObjectManagerHelper($this))->getObject(Label::class);
        $label->setData([
            LabelInterface::LABEL_ID => $id,
            LabelInterface::CODE => 'label-' . $id,
            LabelInterface::PRIORITY => $priority,
            LabelInterface::SHOW_ON_PLP => (int)$plp,
            LabelInterface::SHOW_ON_PDP => (int)$pdp,
            LabelInterface::IS_ACTIVE => 1,
        ]);
        return $label;
    }

    public function testDisabledStoreReturnsNothing(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->seed([10 => $this->makeLabel(10, 1)], [10]);
        $this->assertSame(
            [],
            $this->resolver->getLabelsForProduct(self::PRODUCT_ID, self::STORE_ID, LabelResolver::CONTEXT_PLP)
        );
    }

    public function testPlacementFilterPriorityOrderAndDeduplication(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMaxLabelsPerProduct')->willReturn(0);
        $labels = [
            10 => $this->makeLabel(10, 30),
            11 => $this->makeLabel(11, 10, false, true), // hidden on PLP
            12 => $this->makeLabel(12, 20),
        ];
        // 10 appears twice (manual + computed) and must dedupe; 99 has no definition
        $this->seed($labels, [10, 11, 12, 10, 99]);

        $plp = $this->resolver->getLabelsForProduct(self::PRODUCT_ID, self::STORE_ID, LabelResolver::CONTEXT_PLP);
        $this->assertSame([12, 10], array_map(static fn ($l) => $l->getLabelId(), $plp));

        $pdp = $this->resolver->getLabelsForProduct(self::PRODUCT_ID, self::STORE_ID, LabelResolver::CONTEXT_PDP);
        $this->assertSame([11, 12, 10], array_map(static fn ($l) => $l->getLabelId(), $pdp));

        $any = $this->resolver->getLabelsForProduct(self::PRODUCT_ID, self::STORE_ID, LabelResolver::CONTEXT_ANY);
        $this->assertCount(3, $any);
    }

    public function testMaxLabelsCapKeepsHighestPriority(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMaxLabelsPerProduct')->willReturn(2);
        $this->seed(
            [
                10 => $this->makeLabel(10, 30),
                11 => $this->makeLabel(11, 10),
                12 => $this->makeLabel(12, 20),
            ],
            [10, 11, 12]
        );
        $result = $this->resolver->getLabelsForProduct(self::PRODUCT_ID, self::STORE_ID, LabelResolver::CONTEXT_PDP);
        $this->assertSame([11, 12], array_map(static fn ($l) => $l->getLabelId(), $result));
    }

    public function testEqualPriorityTieBreaksByLabelId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMaxLabelsPerProduct')->willReturn(0);
        $this->seed(
            [12 => $this->makeLabel(12, 10), 10 => $this->makeLabel(10, 10)],
            [12, 10]
        );
        $result = $this->resolver->getLabelsForProduct(self::PRODUCT_ID, self::STORE_ID, LabelResolver::CONTEXT_PDP);
        $this->assertSame([10, 12], array_map(static fn ($l) => $l->getLabelId(), $result));
    }
}

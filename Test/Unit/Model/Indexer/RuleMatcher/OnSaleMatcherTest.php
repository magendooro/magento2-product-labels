<?php
/**
 * Magendoo ProductLabels - OnSaleMatcher unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Test\Unit\Model\Indexer\RuleMatcher;

use Magendoo\ProductLabels\Model\Indexer\RuleMatcher\OnSaleMatcher;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnSaleMatcher::class)]
class OnSaleMatcherTest extends TestCase
{
    /**
     * @var Select|MockObject
     */
    private Select|MockObject $select;

    /**
     * First string argument of every where() call, in call order
     *
     * @var string[]
     */
    private array $whereConditions = [];

    /**
     * @var OnSaleMatcher
     */
    private OnSaleMatcher $matcher;

    protected function setUp(): void
    {
        $this->select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->select->method('from')->willReturnSelf();
        $this->select->method('joinLeft')->willReturnSelf();
        $conditions = &$this->whereConditions;
        $this->select->method('where')->willReturnCallback(
            function (string $condition) use (&$conditions) {
                $conditions[] = $condition;
                return $this->select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select);
        $connection->method('fetchCol')->willReturn(['5', '7']);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $attributeIds = [
            'special_price' => 77,
            'special_from_date' => 78,
            'special_to_date' => 79,
            'price' => 75,
        ];
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturnCallback(
            function (string $entityType, string $attributeCode) use ($attributeIds): AbstractAttribute {
                $attribute = $this->createMock(AbstractAttribute::class);
                $attribute->method('getBackendTable')->willReturn('catalog_product_entity_decimal');
                $attribute->method('getAttributeId')->willReturn($attributeIds[$attributeCode]);
                return $attribute;
            }
        );

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('scopeDate')->willReturn(new \DateTime('2026-01-15 12:00:00'));

        $this->matcher = new OnSaleMatcher($resourceConnection, $eavConfig, $localeDate);
    }

    public function testMatchesOnlyWhenSpecialPriceIsBelowRegularPrice(): void
    {
        $this->assertSame([5, 7], $this->matcher->getMatchingProductIds(1));
        $this->assertContains(
            "(e.type_id = 'bundle' AND IF(sp_s.value_id IS NOT NULL, sp_s.value, sp_d.value) < 100)"
            . " OR (e.type_id <> 'bundle' AND IF(sp_s.value_id IS NOT NULL, sp_s.value, sp_d.value)"
            . ' < IF(pr_s.value_id IS NOT NULL, pr_s.value, pr_d.value))',
            $this->whereConditions
        );
        $this->assertContains(
            'IF(sp_s.value_id IS NOT NULL, sp_s.value, sp_d.value) IS NOT NULL',
            $this->whereConditions
        );
    }
}

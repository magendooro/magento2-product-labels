<?php
/**
 * Magendoo ProductLabels - Shared EAV matcher plumbing
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Indexer\RuleMatcher;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

abstract class AbstractEavMatcher implements MatcherInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param EavConfig $eavConfig
     * @param TimezoneInterface $localeDate
     */
    public function __construct(
        protected readonly ResourceConnection $resourceConnection,
        protected readonly EavConfig $eavConfig,
        protected readonly TimezoneInterface $localeDate
    ) {
    }

    /**
     * Join an EAV attribute with store-value-falls-back-to-default semantics.
     *
     * Adds two LEFT JOINs (store row, default row) and returns the SQL
     * expression yielding the effective value: the store row when one exists
     * (even if its value is NULL — an explicit store override), else default.
     *
     * @param Select $select
     * @param string $attributeCode
     * @param int $storeId
     * @param string $aliasPrefix unique per attribute within the select
     * @return string value expression
     */
    protected function joinAttribute(Select $select, string $attributeCode, int $storeId, string $aliasPrefix): string
    {
        $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $table = $attribute->getBackendTable();
        $attributeId = (int)$attribute->getAttributeId();
        $storeAlias = $aliasPrefix . '_s';
        $defaultAlias = $aliasPrefix . '_d';
        $select->joinLeft(
            [$storeAlias => $table],
            sprintf(
                '%1$s.entity_id = e.entity_id AND %1$s.attribute_id = %2$d AND %1$s.store_id = %3$d',
                $storeAlias,
                $attributeId,
                $storeId
            ),
            []
        );
        $select->joinLeft(
            [$defaultAlias => $table],
            sprintf(
                '%1$s.entity_id = e.entity_id AND %1$s.attribute_id = %2$d AND %1$s.store_id = 0',
                $defaultAlias,
                $attributeId
            ),
            []
        );
        return sprintf('IF(%1$s.value_id IS NOT NULL, %1$s.value, %2$s.value)', $storeAlias, $defaultAlias);
    }

    /**
     * Base select over the product entity table
     *
     * @param int[]|null $productIds
     * @return Select
     */
    protected function createBaseSelect(?array $productIds): Select
    {
        $select = $this->getConnection()->select()
            ->from(['e' => $this->resourceConnection->getTableName('catalog_product_entity')], ['entity_id']);
        if ($productIds !== null) {
            $select->where('e.entity_id IN (?)', array_map('intval', $productIds));
        }
        return $select;
    }

    /**
     * Current store-local date-time, for comparing against store-local EAV date values
     *
     * @param int $storeId
     * @return string Y-m-d H:i:s
     */
    protected function getStoreNow(int $storeId): string
    {
        return $this->localeDate->scopeDate($storeId, null, true)->format('Y-m-d H:i:s');
    }

    /**
     * Default connection used for all matcher queries
     *
     * @return AdapterInterface
     */
    protected function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}

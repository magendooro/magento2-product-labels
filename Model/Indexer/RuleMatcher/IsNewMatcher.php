<?php
/**
 * Magendoo ProductLabels - "New product" rule matcher
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Indexer\RuleMatcher;

/**
 * Standard Magento "new" semantics: the product's news_from_date/news_to_date
 * window contains now, and at least one of the two dates is set.
 */
class IsNewMatcher extends AbstractEavMatcher
{
    /**
     * @inheritdoc
     */
    public function getMatchingProductIds(int $storeId, ?array $productIds = null): array
    {
        $connection = $this->getConnection();
        $select = $this->createBaseSelect($productIds);
        $from = $this->joinAttribute($select, 'news_from_date', $storeId, 'nf');
        $to = $this->joinAttribute($select, 'news_to_date', $storeId, 'nt');
        $now = $this->getStoreNow($storeId);
        $select->where(sprintf('NOT (%s IS NULL AND %s IS NULL)', $from, $to));
        $select->where(sprintf('(%s IS NULL OR %s <= ?)', $from, $from), $now);
        $select->where(sprintf('(%s IS NULL OR %s >= ?)', $to, $to), $now);
        return array_map('intval', $connection->fetchCol($select));
    }
}

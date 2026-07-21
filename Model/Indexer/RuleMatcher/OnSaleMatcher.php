<?php
/**
 * Magendoo ProductLabels - "On sale" rule matcher
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Indexer\RuleMatcher;

/**
 * A product is "on sale" when it has a special_price strictly below its
 * regular price and the special_from_date/special_to_date window is active.
 * Mirrors core special price semantics: the to-date is inclusive through the
 * end of that day, and a special price that does not undercut the regular
 * price is not a discount.
 *
 * Known R1 limitation (documented): catalog price rule discounts are not
 * detected — only special prices.
 */
class OnSaleMatcher extends AbstractEavMatcher
{
    /**
     * @inheritdoc
     */
    public function getMatchingProductIds(int $storeId, ?array $productIds = null): array
    {
        $connection = $this->getConnection();
        $select = $this->createBaseSelect($productIds);
        $price = $this->joinAttribute($select, 'special_price', $storeId, 'sp');
        $from = $this->joinAttribute($select, 'special_from_date', $storeId, 'sf');
        $to = $this->joinAttribute($select, 'special_to_date', $storeId, 'st');
        $regular = $this->joinAttribute($select, 'price', $storeId, 'pr');
        $now = $this->getStoreNow($storeId);
        $select->where(sprintf('%s IS NOT NULL', $price));
        $select->where(sprintf('%s < %s', $price, $regular));
        $select->where(sprintf('(%s IS NULL OR %s <= ?)', $from, $from), $now);
        $select->where(sprintf('(%s IS NULL OR ? < %s + INTERVAL 1 DAY)', $to, $to), $now);
        return array_map('intval', $connection->fetchCol($select));
    }
}

<?php
/**
 * Magendoo ProductLabels - Rule matcher interface
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Indexer\RuleMatcher;

/**
 * A matcher answers: which products satisfy this rule type in this store view
 * right now. Implementations query EAV value tables directly (read-only) with
 * store-value-falls-back-to-default semantics.
 */
interface MatcherInterface
{
    /**
     * Product IDs matching the rule for a store view
     *
     * @param int $storeId
     * @param int[]|null $productIds restrict to these products (null = all)
     * @return int[]
     */
    public function getMatchingProductIds(int $storeId, ?array $productIds = null): array;
}

<?php
/**
 * Magendoo ProductLabels - Computed assignment indexer
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Indexer;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Model\Indexer\RuleMatcher\MatcherInterface;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sole owner of magendoo_product_label_assignment: for every active label
 * with a computed rule type, materializes the (label, product, store) rows
 * the storefront resolver reads. Manual assignments (the product attribute)
 * are merchandiser-owned and never touched here.
 *
 * Full and partial (product-ID-scoped) modes share one diff-based sync so
 * FPC is only flushed for products whose label set actually changed.
 */
class LabelAssignment implements
    \Magento\Framework\Indexer\ActionInterface,
    \Magento\Framework\Mview\ActionInterface
{
    public const INDEXER_ID = 'magendoo_product_labels';

    private const ASSIGNMENT_TABLE = 'magendoo_product_label_assignment';
    private const INSERT_CHUNK = 1000;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $labelCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CacheContext $cacheContext
     * @param EventManagerInterface $eventManager
     * @param MatcherInterface[] $matchers rule_type => matcher (DI-wired)
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CollectionFactory $labelCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheContext $cacheContext,
        private readonly EventManagerInterface $eventManager,
        private readonly array $matchers = []
    ) {
    }

    /**
     * @inheritdoc
     */
    public function executeFull()
    {
        $this->reindex(null);
    }

    /**
     * @inheritdoc
     */
    public function executeList(array $ids)
    {
        $this->reindex($ids);
    }

    /**
     * @inheritdoc
     */
    public function executeRow($id)
    {
        $this->reindex([(int)$id]);
    }

    /**
     * Mview entry point: rebuild computed assignments for the changed product ids.
     *
     * @param int[] $ids
     * @return void
     */
    public function execute($ids)
    {
        $this->reindex(array_map('intval', (array)$ids));
    }

    /**
     * Rebuild computed assignments, scoped to the given product IDs (null = all)
     *
     * @param int[]|null $productIds
     * @return void
     */
    private function reindex(?array $productIds): void
    {
        if ($productIds !== null) {
            $productIds = array_values(array_unique(array_filter($productIds)));
            if (!$productIds) {
                return;
            }
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::ASSIGNMENT_TABLE);
        $changedProductIds = [];

        $computedLabels = $this->getComputedLabels();
        $this->purgeStaleLabels($computedLabels, $changedProductIds);

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            foreach ($computedLabels as $labelId => $ruleType) {
                $matcher = $this->matchers[$ruleType] ?? null;
                if (!$matcher instanceof MatcherInterface) {
                    continue;
                }
                $matched = $matcher->getMatchingProductIds($storeId, $productIds);

                $existingSelect = $connection->select()
                    ->from($table, ['product_id'])
                    ->where('label_id = ?', $labelId)
                    ->where('store_id = ?', $storeId);
                if ($productIds !== null) {
                    $existingSelect->where('product_id IN (?)', $productIds);
                }
                $existing = array_map('intval', $connection->fetchCol($existingSelect));

                $toInsert = array_diff($matched, $existing);
                $toDelete = array_diff($existing, $matched);

                if ($toDelete) {
                    $connection->delete($table, [
                        'label_id = ?' => $labelId,
                        'store_id = ?' => $storeId,
                        'product_id IN (?)' => array_values($toDelete),
                    ]);
                }
                foreach (array_chunk(array_values($toInsert), self::INSERT_CHUNK) as $chunk) {
                    $rows = [];
                    foreach ($chunk as $productId) {
                        $rows[] = ['label_id' => $labelId, 'product_id' => $productId, 'store_id' => $storeId];
                    }
                    $connection->insertMultiple($table, $rows);
                }
                foreach ($toInsert as $productId) {
                    $changedProductIds[$productId] = true;
                }
                foreach ($toDelete as $productId) {
                    $changedProductIds[$productId] = true;
                }
            }
        }

        $this->flushProductCache(array_keys($changedProductIds));
    }

    /**
     * Active labels with a computed rule, as [label_id => rule_type]
     *
     * @return array<int, string>
     */
    private function getComputedLabels(): array
    {
        $collection = $this->labelCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addFieldToFilter(LabelInterface::RULE_TYPE, ['neq' => LabelInterface::RULE_TYPE_NONE]);
        $result = [];
        foreach ($collection as $label) {
            $result[(int)$label->getId()] = (string)$label->getData(LabelInterface::RULE_TYPE);
        }
        return $result;
    }

    /**
     * Drop assignment rows whose label is gone, inactive, or manual-only now
     *
     * @param array $computedLabels
     * @param array $changedProductIds accumulates affected products
     * @return void
     */
    private function purgeStaleLabels(array $computedLabels, array &$changedProductIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::ASSIGNMENT_TABLE);
        $select = $connection->select()->from($table, ['product_id'])->distinct();
        $condition = null;
        if ($computedLabels) {
            $select->where('label_id NOT IN (?)', array_keys($computedLabels));
            $condition = ['label_id NOT IN (?)' => array_keys($computedLabels)];
        }
        $stale = array_map('intval', $connection->fetchCol($select));
        if (!$stale) {
            return;
        }
        $connection->delete($table, $condition ?? '1=1');
        foreach ($stale as $productId) {
            $changedProductIds[$productId] = true;
        }
    }

    /**
     * Flush FPC/block cache for products whose label set changed
     *
     * @param int[] $productIds
     * @return void
     */
    private function flushProductCache(array $productIds): void
    {
        if (!$productIds) {
            return;
        }
        $this->cacheContext->registerEntities(Product::CACHE_TAG, $productIds);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $this->cacheContext]);
    }
}

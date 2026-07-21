<?php
/**
 * Magendoo ProductLabels - Storefront label resolution service
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magendoo\ProductLabels\Setup\Patch\Data\AddProductLabelsAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;

/**
 * Resolves the labels to render for products: the union of manual assignments
 * (the magendoo_product_labels attribute, merchandiser-owned) and computed
 * assignments (magendoo_product_label_assignment, indexer-owned), filtered by
 * active flag and placement, ordered by priority, capped by configuration.
 *
 * Batch-oriented: preload() fetches assignments for many products in two
 * queries; per-product lookups then hit the request cache. Label definitions
 * (a small table) are loaded once per request per store.
 */
class LabelResolver
{
    public const CONTEXT_PLP = 'plp';
    public const CONTEXT_PDP = 'pdp';

    /**
     * No placement filtering — used by GraphQL, where the client decides placement.
     */
    public const CONTEXT_ANY = 'any';

    /**
     * @var array<int, array<int, Label>> [storeId => [labelId => Label with store text applied]]
     */
    private array $activeLabels = [];

    /**
     * @var array<int, array<int, int[]>> [storeId => [productId => labelIds]]
     */
    private array $assignments = [];

    /**
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $labelCollectionFactory
     * @param EavConfig $eavConfig
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CollectionFactory $labelCollectionFactory,
        private readonly EavConfig $eavConfig,
        private readonly Config $config
    ) {
    }

    /**
     * Batch-load assignments for a set of products in one pass
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return void
     */
    public function preload(array $productIds, int $storeId): void
    {
        if (!$this->config->isEnabled($storeId)) {
            return;
        }
        $productIds = array_map('intval', $productIds);
        $missing = array_diff($productIds, array_keys($this->assignments[$storeId] ?? []));
        if (!$missing) {
            return;
        }
        foreach ($missing as $productId) {
            $this->assignments[$storeId][$productId] = [];
        }
        if (!$this->getActiveLabels($storeId)) {
            return;
        }
        $this->loadManualAssignments($missing, $storeId);
        $this->loadComputedAssignments($missing, $storeId);
    }

    /**
     * Labels to render for one product in a placement context
     *
     * @param int $productId
     * @param int $storeId
     * @param string $context self::CONTEXT_PLP|self::CONTEXT_PDP
     * @return Label[] ordered by priority, capped by max_labels_per_product
     */
    public function getLabelsForProduct(int $productId, int $storeId, string $context): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }
        if (!isset($this->assignments[$storeId][$productId])) {
            $this->preload([$productId], $storeId);
        }
        $labels = [];
        $active = $this->getActiveLabels($storeId);
        foreach (array_unique($this->assignments[$storeId][$productId] ?? []) as $labelId) {
            $label = $active[$labelId] ?? null;
            if ($label === null) {
                continue;
            }
            if ($context === self::CONTEXT_PLP && !$label->isShowOnPlp()) {
                continue;
            }
            if ($context === self::CONTEXT_PDP && !$label->isShowOnPdp()) {
                continue;
            }
            $labels[] = $label;
        }
        usort(
            $labels,
            static fn (Label $a, Label $b) => $a->getPriority() <=> $b->getPriority()
                ?: $a->getLabelId() <=> $b->getLabelId()
        );
        $max = $this->config->getMaxLabelsPerProduct($storeId);
        return $max > 0 ? array_slice($labels, 0, $max) : $labels;
    }

    /**
     * Active label definitions with store text applied, keyed by label_id
     *
     * @param int $storeId
     * @return array<int, Label>
     */
    public function getActiveLabels(int $storeId): array
    {
        if (!isset($this->activeLabels[$storeId])) {
            $labels = [];
            $collection = $this->labelCollectionFactory->create();
            $collection->addActiveFilter();
            /** @var Label $label */
            foreach ($collection as $label) {
                $labels[(int)$label->getId()] = $label;
            }
            if ($labels) {
                $this->applyStoreTexts($labels, $storeId);
            }
            $this->activeLabels[$storeId] = $labels;
        }
        return $this->activeLabels[$storeId];
    }

    /**
     * Overlay per-store text overrides onto collection-loaded labels
     *
     * @param array<int, Label> $labels
     * @param int $storeId
     * @return void
     */
    private function applyStoreTexts(array $labels, int $storeId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('magendoo_product_label_store'), ['label_id', 'label_text'])
            ->where('label_id IN (?)', array_keys($labels))
            ->where('store_id = ?', $storeId);
        foreach ($connection->fetchPairs($select) as $labelId => $text) {
            if (isset($labels[(int)$labelId]) && $text !== '') {
                $labels[(int)$labelId]->setData(LabelInterface::LABEL_TEXT, $text);
            }
        }
    }

    /**
     * Manual assignments: parse the global multiselect attribute values
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return void
     */
    private function loadManualAssignments(array $productIds, int $storeId): void
    {
        $attribute = $this->eavConfig->getAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            AddProductLabelsAttribute::ATTRIBUTE_CODE
        );
        if (!$attribute || !$attribute->getAttributeId()) {
            return;
        }
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($attribute->getBackendTable(), ['entity_id', 'value'])
            ->where('attribute_id = ?', (int)$attribute->getAttributeId())
            ->where('store_id = ?', 0)
            ->where('entity_id IN (?)', $productIds);
        foreach ($connection->fetchPairs($select) as $productId => $value) {
            foreach (explode(',', (string)$value) as $labelId) {
                if ((int)$labelId > 0) {
                    $this->assignments[$storeId][(int)$productId][] = (int)$labelId;
                }
            }
        }
    }

    /**
     * Computed assignments from the indexer-owned table
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return void
     */
    private function loadComputedAssignments(array $productIds, int $storeId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('magendoo_product_label_assignment'),
                ['product_id', 'label_id']
            )
            ->where('store_id = ?', $storeId)
            ->where('product_id IN (?)', $productIds);
        foreach ($connection->fetchAll($select) as $row) {
            $this->assignments[$storeId][(int)$row['product_id']][] = (int)$row['label_id'];
        }
    }
}

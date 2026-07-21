<?php
/**
 * Magendoo ProductLabels - Label repository
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Api\Data\LabelSearchResultsInterface;
use Magendoo\ProductLabels\Api\Data\LabelSearchResultsInterfaceFactory;
use Magendoo\ProductLabels\Api\LabelRepositoryInterface;
use Magendoo\ProductLabels\Model\Indexer\LabelAssignment as LabelAssignmentIndexer;
use Magendoo\ProductLabels\Model\ResourceModel\Label as LabelResource;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magendoo\ProductLabels\Setup\Patch\Data\AddProductLabelsAttribute;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\IndexerRegistry;

class LabelRepository implements LabelRepositoryInterface
{
    /**
     * @param LabelResource $resource
     * @param LabelFactory $labelFactory
     * @param CollectionFactory $collectionFactory
     * @param LabelSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param IndexerRegistry $indexerRegistry
     * @param EavConfig $eavConfig
     * @param CacheContext $cacheContext
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        private readonly LabelResource $resource,
        private readonly LabelFactory $labelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LabelSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly EavConfig $eavConfig,
        private readonly CacheContext $cacheContext,
        private readonly EventManagerInterface $eventManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(LabelInterface $label): LabelInterface
    {
        /** @var Label $label */
        $needsReindex = $this->affectsComputedAssignments($label);
        $visibilityChanged = $this->visibilityChanged($label);
        try {
            $this->resource->save($label);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the label: %1', $e->getMessage()), $e);
        }
        if ($needsReindex) {
            $this->invalidateIndexer();
        }
        if ($visibilityChanged) {
            $this->flushAssigneePages((int)$label->getId());
        }
        return $label;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $labelId): LabelInterface
    {
        $label = $this->labelFactory->create();
        $this->resource->load($label, $labelId);
        if (!$label->getId()) {
            throw new NoSuchEntityException(__('The label with the "%1" ID doesn\'t exist.', $labelId));
        }
        return $label;
    }

    /**
     * @inheritdoc
     */
    public function getByCode(string $code): LabelInterface
    {
        $label = $this->labelFactory->create();
        $this->resource->loadByCode($label, $code);
        if (!$label->getId()) {
            throw new NoSuchEntityException(__('The label with the "%1" code doesn\'t exist.', $code));
        }
        return $label;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LabelSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        /** @var LabelSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(LabelInterface $label): bool
    {
        /** @var Label $label */
        $hadComputedRule = $label->getRuleType() !== LabelInterface::RULE_TYPE_NONE;
        try {
            $this->resource->delete($label);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the label: %1', $e->getMessage()), $e);
        }
        if ($hadComputedRule) {
            $this->invalidateIndexer();
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $labelId): bool
    {
        return $this->delete($this->getById($labelId));
    }

    /**
     * Whether this save can change computed assignment rows.
     *
     * Display-only edits (text, colors, position) don't touch the assignment
     * table — FPC invalidation via the label's cache identity covers those.
     *
     * @param Label $label
     * @return bool
     */
    private function affectsComputedAssignments(Label $label): bool
    {
        $ruleType = $label->getRuleType();
        $origRuleType = (string)$label->getOrigData(LabelInterface::RULE_TYPE);
        if ($label->isObjectNew() || $origRuleType === '') {
            return $ruleType !== LabelInterface::RULE_TYPE_NONE;
        }
        if ($ruleType !== $origRuleType) {
            return true;
        }
        $activeChanged = (bool)$label->getOrigData(LabelInterface::IS_ACTIVE) !== $label->isActive();
        return $activeChanged && $ruleType !== LabelInterface::RULE_TYPE_NONE;
    }

    /**
     * Whether this save switches the label's storefront visibility on or off.
     *
     * Display-only edits are covered by the label's own identity tag on the
     * pages that rendered it; visibility TRANSITIONS also affect pages that
     * did NOT render it, which carry no such tag.
     *
     * @param Label $label
     * @return bool
     */
    private function visibilityChanged(Label $label): bool
    {
        if ($label->isObjectNew() || $label->getOrigData(LabelInterface::LABEL_ID) === null) {
            return false;
        }
        return (bool)$label->getOrigData(LabelInterface::IS_ACTIVE) !== $label->isActive()
            || (bool)$label->getOrigData(LabelInterface::SHOW_ON_PLP) !== $label->isShowOnPlp()
            || (bool)$label->getOrigData(LabelInterface::SHOW_ON_PDP) !== $label->isShowOnPdp();
    }

    /**
     * Flush the cached pages of every product the label is assigned to.
     *
     * Covers both assignment surfaces directly: the manual multiselect
     * attribute and the indexer-owned assignment table. The assignment table
     * must be read here rather than left to the indexer diff sync, because
     * its rows are placement-agnostic — a show_on_plp/show_on_pdp transition
     * changes no rows, so a reindex would produce an empty diff and flush
     * nothing.
     *
     * @param int $labelId
     * @return void
     */
    private function flushAssigneePages(int $labelId): void
    {
        $productIds = array_values(array_unique(array_merge(
            $this->getManualAssigneeIds($labelId),
            $this->getComputedAssigneeIds($labelId)
        )));
        if (!$productIds) {
            return;
        }
        $this->cacheContext->registerEntities(Product::CACHE_TAG, $productIds);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $this->cacheContext]);
    }

    /**
     * Products holding the label in the manual multiselect attribute.
     *
     * Manual assignments live on the global (store 0) row of the
     * magendoo_product_labels attribute; FIND_IN_SET is exact-element on the
     * comma-separated value.
     *
     * @param int $labelId
     * @return int[]
     */
    private function getManualAssigneeIds(int $labelId): array
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, AddProductLabelsAttribute::ATTRIBUTE_CODE);
        if (!$attribute || !$attribute->getAttributeId()) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTable('catalog_product_entity_varchar'), ['entity_id'])
            ->where('attribute_id = ?', (int)$attribute->getAttributeId())
            ->where('store_id = ?', 0)
            ->where('FIND_IN_SET(?, value)', (string)$labelId);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Products holding the label in the indexer-owned assignment table.
     *
     * @param int $labelId
     * @return int[]
     */
    private function getComputedAssigneeIds(int $labelId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTable('magendoo_product_label_assignment'), ['product_id'])
            ->distinct()
            ->where('label_id = ?', $labelId);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Mark the assignment indexer invalid so cron (or a manual reindex) rebuilds it
     *
     * @return void
     */
    private function invalidateIndexer(): void
    {
        $this->indexerRegistry->get(LabelAssignmentIndexer::INDEXER_ID)->invalidate();
    }
}

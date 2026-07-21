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
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
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
     */
    public function __construct(
        private readonly LabelResource $resource,
        private readonly LabelFactory $labelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LabelSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(LabelInterface $label): LabelInterface
    {
        /** @var Label $label */
        $needsReindex = $this->affectsComputedAssignments($label);
        try {
            $this->resource->save($label);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the label: %1', $e->getMessage()), $e);
        }
        if ($needsReindex) {
            $this->invalidateIndexer();
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
     * Mark the assignment indexer invalid so cron (or a manual reindex) rebuilds it
     *
     * @return void
     */
    private function invalidateIndexer(): void
    {
        $this->indexerRegistry->get(LabelAssignmentIndexer::INDEXER_ID)->invalidate();
    }
}

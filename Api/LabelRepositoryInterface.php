<?php
/**
 * Magendoo ProductLabels - Label repository interface
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Api;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Api\Data\LabelSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * @api
 */
interface LabelRepositoryInterface
{
    /**
     * Save a label
     *
     * @param \Magendoo\ProductLabels\Api\Data\LabelInterface $label
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(LabelInterface $label): LabelInterface;

    /**
     * Load a label by ID
     *
     * @param int $labelId
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $labelId): LabelInterface;

    /**
     * Load a label by its stable code
     *
     * @param string $code
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByCode(string $code): LabelInterface;

    /**
     * Retrieve labels matching the given criteria
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magendoo\ProductLabels\Api\Data\LabelSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LabelSearchResultsInterface;

    /**
     * Delete a label
     *
     * @param \Magendoo\ProductLabels\Api\Data\LabelInterface $label
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(LabelInterface $label): bool;

    /**
     * Delete a label by ID
     *
     * @param int $labelId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $labelId): bool;
}

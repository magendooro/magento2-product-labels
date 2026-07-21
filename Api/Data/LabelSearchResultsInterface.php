<?php
/**
 * Magendoo ProductLabels - Label search results interface
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface LabelSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get label list
     *
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface[]
     */
    public function getItems();

    /**
     * Set label list
     *
     * @param \Magendoo\ProductLabels\Api\Data\LabelInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}

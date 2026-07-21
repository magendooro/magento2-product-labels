<?php
/**
 * Magendoo ProductLabels - Label search results
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model;

use Magendoo\ProductLabels\Api\Data\LabelSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class LabelSearchResults extends SearchResults implements LabelSearchResultsInterface
{
}

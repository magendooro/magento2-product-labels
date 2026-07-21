<?php
/**
 * Magendoo ProductLabels - Module configuration reader
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'magendoo_productlabels/general/enabled';
    public const XML_PATH_MAX_LABELS = 'magendoo_productlabels/general/max_labels_per_product';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether label rendering is enabled for a store view
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Maximum number of badges rendered per product (0 = unlimited)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxLabelsPerProduct(?int $storeId = null): int
    {
        return max(0, (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_LABELS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }
}

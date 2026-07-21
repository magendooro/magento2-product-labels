<?php
/**
 * Magendoo ProductLabels - Preload labels for product list pages
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Observer;

use Magendoo\ProductLabels\Model\Config;
use Magendoo\ProductLabels\Model\LabelResolver;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * catalog_block_product_list_collection fires with the loaded PLP collection;
 * one preload here means the per-tile image plugin only reads request cache.
 */
class PreloadListLabelsObserver implements ObserverInterface
{
    /**
     * @param LabelResolver $labelResolver
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly LabelResolver $labelResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return;
        }
        $collection = $observer->getEvent()->getData('collection');
        if (!$collection instanceof \Magento\Framework\Data\Collection) {
            return;
        }
        $productIds = [];
        foreach ($collection as $product) {
            $productIds[] = (int)$product->getId();
        }
        if ($productIds) {
            $this->labelResolver->preload($productIds, $storeId);
        }
    }
}

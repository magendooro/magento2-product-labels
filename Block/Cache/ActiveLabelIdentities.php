<?php
/**
 * Magendoo ProductLabels - Listing page cache identity block
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Block\Cache;

use Magendoo\ProductLabels\Model\Config;
use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\LabelResolver;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders nothing; exists only to stamp listing pages (category, search)
 * with the cache tags of every active label. Product tiles are rendered
 * through the ImageFactory plugin, which cannot contribute page identities,
 * so without this block an edited label text/color would never flush
 * already-cached listing pages.
 */
class ActiveLabelIdentities extends AbstractBlock implements IdentityInterface
{
    /**
     * @param Context $context
     * @param LabelResolver $labelResolver
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly LabelResolver $labelResolver,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities()
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }
        $identities = [];
        foreach (array_keys($this->labelResolver->getActiveLabels($storeId)) as $labelId) {
            $identities[] = Label::CACHE_TAG . '_' . $labelId;
        }
        return $identities;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        return '';
    }
}

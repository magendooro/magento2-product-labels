<?php
/**
 * Magendoo ProductLabels - Listing page cache identity block
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Block\Cache;

use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;

/**
 * Renders nothing; exists only to stamp listing pages (category, search)
 * with the cache tags of every label definition. Product tiles are rendered
 * through the ImageFactory plugin, which cannot contribute page identities,
 * so without this block an edited label text/color would never flush
 * already-cached listing pages.
 *
 * Every definition — active AND inactive — is stamped: pages only carry
 * tags for labels they rendered, so stamping only active labels meant a
 * label being re-activated (or getting a placement flag enabled) flushed
 * nothing. Stamping every definition makes listing pages flush on
 * transitions in both directions.
 */
class ActiveLabelIdentities extends AbstractBlock implements IdentityInterface
{
    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities()
    {
        $identities = [];
        foreach ($this->collectionFactory->create()->getAllIds() as $labelId) {
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

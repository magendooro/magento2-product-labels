<?php
/**
 * Magendoo ProductLabels - Product attribute source model
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Attribute\Source;

use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Options for the magendoo_product_labels multiselect attribute, backed by the
 * label entity table. The stored option values are label_ids — local to this
 * installation by design; the stable `code` lives on the label entity.
 *
 * Inactive labels stay listed so historical selections remain visible in the
 * admin; the storefront resolver filters inactive labels at render time.
 */
class ProductLabels extends AbstractSource
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [];
            $collection = $this->collectionFactory->create();
            $collection->setOrder('name', 'ASC');
            foreach ($collection as $label) {
                $name = (string)$label->getName();
                if (!(bool)$label->getData('is_active')) {
                    $name .= ' (' . __('inactive') . ')';
                }
                $this->_options[] = [
                    'label' => $name,
                    'value' => (string)$label->getId(),
                ];
            }
        }
        return $this->_options;
    }
}

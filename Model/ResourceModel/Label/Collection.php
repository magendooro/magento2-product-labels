<?php
/**
 * Magendoo ProductLabels - Label collection
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\ResourceModel\Label;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'label_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'magendoo_productlabels_label_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'label_collection';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(
            \Magendoo\ProductLabels\Model\Label::class,
            \Magendoo\ProductLabels\Model\ResourceModel\Label::class
        );
    }

    /**
     * Keep only active labels
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        return $this->addFieldToFilter('is_active', 1);
    }
}

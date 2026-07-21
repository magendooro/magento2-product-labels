<?php
/**
 * Magendoo ProductLabels - Delete button
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Block\Adminhtml\Label\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getButtonData(): array
    {
        if (!$this->getLabelId()) {
            return [];
        }
        return [
            'label' => __('Delete Label'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this label?'),
                $this->getUrl('*/*/delete', ['label_id' => $this->getLabelId()])
            ),
            'sort_order' => 20,
        ];
    }
}

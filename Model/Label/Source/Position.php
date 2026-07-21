<?php
/**
 * Magendoo ProductLabels - Badge position options
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Label\Source;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Position implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => LabelInterface::POSITION_TOP_LEFT, 'label' => __('Top Left')],
            ['value' => LabelInterface::POSITION_TOP_RIGHT, 'label' => __('Top Right')],
            ['value' => LabelInterface::POSITION_BOTTOM_LEFT, 'label' => __('Bottom Left')],
            ['value' => LabelInterface::POSITION_BOTTOM_RIGHT, 'label' => __('Bottom Right')],
        ];
    }
}

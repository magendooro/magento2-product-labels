<?php
/**
 * Magendoo ProductLabels - Rule type options
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Label\Source;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magento\Framework\Data\OptionSourceInterface;

class RuleType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => LabelInterface::RULE_TYPE_NONE, 'label' => __('None (manual assignment only)')],
            ['value' => LabelInterface::RULE_TYPE_IS_NEW, 'label' => __('New products (news from/to dates)')],
            ['value' => LabelInterface::RULE_TYPE_ON_SALE, 'label' => __('On sale (active special price)')],
        ];
    }
}

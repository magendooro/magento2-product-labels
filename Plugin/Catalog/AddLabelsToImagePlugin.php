<?php
/**
 * Magendoo ProductLabels - PLP badge rendering plugin
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Plugin\Catalog;

use Magendoo\ProductLabels\Model\Config;
use Magendoo\ProductLabels\Model\LabelResolver;
use Magento\Catalog\Block\Product\Image as ImageBlock;
use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ImageFactory builds the Image block for every product tile (category,
 * search, related, widgets). When the product has listing labels, attach
 * them and swap in the module template that wraps the stock markup with the
 * badge overlay. Products without labels keep the core template untouched.
 */
class AddLabelsToImagePlugin
{
    private const LABEL_TEMPLATE = 'Magendoo_ProductLabels::product/image_with_labels.phtml';

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
     * Attach label data to the freshly built image block
     *
     * @param ImageFactory $subject
     * @param ImageBlock $result
     * @param Product $product
     * @param string $imageId
     * @param array|null $attributes
     * @return ImageBlock
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreate(
        ImageFactory $subject,
        ImageBlock $result,
        Product $product,
        string $imageId,
        ?array $attributes = null
    ): ImageBlock {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return $result;
        }
        $labels = $this->labelResolver->getLabelsForProduct(
            (int)$product->getId(),
            $storeId,
            LabelResolver::CONTEXT_PLP
        );
        if (!$labels) {
            return $result;
        }
        $labelData = [];
        foreach ($labels as $label) {
            $labelData[] = [
                'code' => $label->getCode(),
                'text' => $label->getLabelText(),
                'text_color' => $label->getTextColor(),
                'background_color' => $label->getBackgroundColor(),
                'position' => $label->getPosition(),
            ];
        }
        $result->setData('magendoo_labels', $labelData);
        $result->setTemplate(self::LABEL_TEMPLATE);
        return $result;
    }
}

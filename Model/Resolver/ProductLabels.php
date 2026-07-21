<?php
/**
 * Magendoo ProductLabels - GraphQL product labels resolver
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\Resolver;

use Magendoo\ProductLabels\Model\LabelResolver;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Deferred (dataloader-style) resolver: every product in the query buffers
 * its ID first; the first deferred to run preloads assignments for the whole
 * batch, so a 20-item products query costs the same two lookups as one item.
 */
class ProductLabels implements ResolverInterface
{
    /**
     * @var array<int, int[]> [storeId => productIds pending preload]
     */
    private array $pendingByStore = [];

    /**
     * @param LabelResolver $labelResolver
     * @param ValueFactory $valueFactory
     */
    public function __construct(
        private readonly LabelResolver $labelResolver,
        private readonly ValueFactory $valueFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($value['model']) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var \Magento\GraphQl\Model\Query\ContextInterface $context */
        $productId = (int)$value['model']->getId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $this->pendingByStore[$storeId][] = $productId;

        return $this->valueFactory->create(function () use ($productId, $storeId) {
            if (!empty($this->pendingByStore[$storeId])) {
                $this->labelResolver->preload($this->pendingByStore[$storeId], $storeId);
                $this->pendingByStore[$storeId] = [];
            }
            $result = [];
            $labels = $this->labelResolver->getLabelsForProduct($productId, $storeId, LabelResolver::CONTEXT_ANY);
            foreach ($labels as $label) {
                $result[] = [
                    'code' => $label->getCode(),
                    'text' => $label->getLabelText(),
                    'text_color' => $label->getTextColor(),
                    'background_color' => $label->getBackgroundColor(),
                    'position' => $label->getPosition(),
                    'priority' => $label->getPriority(),
                ];
            }
            return $result;
        });
    }
}

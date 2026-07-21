<?php
/**
 * Magendoo ProductLabels - PDP badge overlay block
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Block\Product;

use Magendoo\ProductLabels\Model\Config;
use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\LabelResolver;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\View\AbstractView;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Stdlib\ArrayUtils;

/**
 * Renders the badge overlay on the product page media area. Exposes the
 * rendered labels' cache tags so an edited label flushes exactly the product
 * pages that showed it.
 */
class Labels extends AbstractView implements IdentityInterface
{
    /**
     * @var Label[]|null
     */
    private ?array $labels = null;

    /**
     * @param Context $context
     * @param ArrayUtils $arrayUtils
     * @param LabelResolver $labelResolver
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        ArrayUtils $arrayUtils,
        private readonly LabelResolver $labelResolver,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $arrayUtils, $data);
    }

    /**
     * Labels to render for the current product
     *
     * @return Label[]
     */
    public function getLabels(): array
    {
        if ($this->labels === null) {
            $this->labels = [];
            $product = $this->getProduct();
            $storeId = (int)$this->_storeManager->getStore()->getId();
            if ($product && $product->getId() && $this->config->isEnabled($storeId)) {
                $this->labels = $this->labelResolver->getLabelsForProduct(
                    (int)$product->getId(),
                    $storeId,
                    LabelResolver::CONTEXT_PDP
                );
            }
        }
        return $this->labels;
    }

    /**
     * @inheritdoc
     */
    public function getIdentities()
    {
        $identities = [];
        foreach ($this->getLabels() as $label) {
            $identities[] = Label::CACHE_TAG . '_' . $label->getLabelId();
        }
        return $identities;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->getLabels()) {
            return '';
        }
        return parent::_toHtml();
    }
}

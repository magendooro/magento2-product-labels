<?php
/**
 * Magendoo ProductLabels - Label model
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Product label definition.
 *
 * Store-view text overrides travel on the non-contract data key
 * `store_overrides` (array of ['store_id' => int, 'label_text' => string]),
 * loaded and persisted by the resource model.
 */
class Label extends AbstractModel implements LabelInterface, IdentityInterface
{
    /**
     * FPC/block cache tag; a saved/deleted label flushes every page that rendered it.
     */
    public const CACHE_TAG = 'magendoo_pl_l';

    /**
     * Data key carrying store-view text overrides (not part of the Api contract).
     */
    public const DATA_STORE_OVERRIDES = 'store_overrides';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'magendoo_productlabels_label';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(\Magendoo\ProductLabels\Model\ResourceModel\Label::class);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * All valid rule type codes
     *
     * @return string[]
     */
    public static function getAvailableRuleTypes(): array
    {
        return [self::RULE_TYPE_NONE, self::RULE_TYPE_IS_NEW, self::RULE_TYPE_ON_SALE];
    }

    /**
     * All valid badge positions
     *
     * @return string[]
     */
    public static function getAvailablePositions(): array
    {
        return [
            self::POSITION_TOP_LEFT,
            self::POSITION_TOP_RIGHT,
            self::POSITION_BOTTOM_LEFT,
            self::POSITION_BOTTOM_RIGHT,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLabelId(): ?int
    {
        $id = $this->getData(self::LABEL_ID);
        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritdoc
     */
    public function setLabelId(int $labelId): LabelInterface
    {
        return $this->setData(self::LABEL_ID, $labelId);
    }

    /**
     * @inheritdoc
     */
    public function getCode(): ?string
    {
        return $this->getData(self::CODE);
    }

    /**
     * @inheritdoc
     */
    public function setCode(string $code): LabelInterface
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): LabelInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): LabelInterface
    {
        return $this->setData(self::IS_ACTIVE, (int)$isActive);
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return (int)$this->getData(self::PRIORITY);
    }

    /**
     * @inheritdoc
     */
    public function setPriority(int $priority): LabelInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritdoc
     */
    public function getLabelText(): ?string
    {
        return $this->getData(self::LABEL_TEXT);
    }

    /**
     * @inheritdoc
     */
    public function setLabelText(string $labelText): LabelInterface
    {
        return $this->setData(self::LABEL_TEXT, $labelText);
    }

    /**
     * @inheritdoc
     */
    public function getTextColor(): string
    {
        return (string)($this->getData(self::TEXT_COLOR) ?? '#FFFFFF');
    }

    /**
     * @inheritdoc
     */
    public function setTextColor(string $textColor): LabelInterface
    {
        return $this->setData(self::TEXT_COLOR, $textColor);
    }

    /**
     * @inheritdoc
     */
    public function getBackgroundColor(): string
    {
        return (string)($this->getData(self::BACKGROUND_COLOR) ?? '#E02B27');
    }

    /**
     * @inheritdoc
     */
    public function setBackgroundColor(string $backgroundColor): LabelInterface
    {
        return $this->setData(self::BACKGROUND_COLOR, $backgroundColor);
    }

    /**
     * @inheritdoc
     */
    public function getPosition(): string
    {
        return (string)($this->getData(self::POSITION) ?? self::POSITION_TOP_LEFT);
    }

    /**
     * @inheritdoc
     */
    public function setPosition(string $position): LabelInterface
    {
        return $this->setData(self::POSITION, $position);
    }

    /**
     * @inheritdoc
     */
    public function isShowOnPlp(): bool
    {
        return (bool)$this->getData(self::SHOW_ON_PLP);
    }

    /**
     * @inheritdoc
     */
    public function setShowOnPlp(bool $showOnPlp): LabelInterface
    {
        return $this->setData(self::SHOW_ON_PLP, (int)$showOnPlp);
    }

    /**
     * @inheritdoc
     */
    public function isShowOnPdp(): bool
    {
        return (bool)$this->getData(self::SHOW_ON_PDP);
    }

    /**
     * @inheritdoc
     */
    public function setShowOnPdp(bool $showOnPdp): LabelInterface
    {
        return $this->setData(self::SHOW_ON_PDP, (int)$showOnPdp);
    }

    /**
     * @inheritdoc
     */
    public function getRuleType(): string
    {
        return (string)($this->getData(self::RULE_TYPE) ?? self::RULE_TYPE_NONE);
    }

    /**
     * @inheritdoc
     */
    public function setRuleType(string $ruleType): LabelInterface
    {
        return $this->setData(self::RULE_TYPE, $ruleType);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Get store-view text overrides as [store_id => label_text]
     *
     * @return array<int, string>
     */
    public function getStoreOverrides(): array
    {
        $overrides = $this->getData(self::DATA_STORE_OVERRIDES);
        if (!is_array($overrides)) {
            return [];
        }
        $result = [];
        foreach ($overrides as $row) {
            if (isset($row['store_id']) && isset($row['label_text']) && $row['label_text'] !== '') {
                $result[(int)$row['store_id']] = (string)$row['label_text'];
            }
        }
        return $result;
    }

    /**
     * Storefront text for a given store view (override or default)
     *
     * @param int $storeId
     * @return string
     */
    public function getTextForStore(int $storeId): string
    {
        $overrides = $this->getStoreOverrides();
        return $overrides[$storeId] ?? (string)$this->getLabelText();
    }
}

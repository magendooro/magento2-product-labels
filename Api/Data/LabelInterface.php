<?php
/**
 * Magendoo ProductLabels - Label data interface
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Api\Data;

/**
 * Product label definition.
 *
 * The `code` field is the stable cross-system identifier; the numeric label_id
 * is local to one Magento installation and must never be used as an
 * integration contract.
 *
 * @api
 */
interface LabelInterface
{
    public const LABEL_ID = 'label_id';
    public const CODE = 'code';
    public const NAME = 'name';
    public const IS_ACTIVE = 'is_active';
    public const PRIORITY = 'priority';
    public const LABEL_TEXT = 'label_text';
    public const TEXT_COLOR = 'text_color';
    public const BACKGROUND_COLOR = 'background_color';
    public const POSITION = 'position';
    public const SHOW_ON_PLP = 'show_on_plp';
    public const SHOW_ON_PDP = 'show_on_pdp';
    public const RULE_TYPE = 'rule_type';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Rule type: manual assignment only, no computed matching.
     */
    public const RULE_TYPE_NONE = 'none';

    /**
     * Rule type: product is within its news_from_date/news_to_date window.
     */
    public const RULE_TYPE_IS_NEW = 'is_new';

    /**
     * Rule type: product has an active special price window.
     */
    public const RULE_TYPE_ON_SALE = 'on_sale';

    public const POSITION_TOP_LEFT = 'top-left';
    public const POSITION_TOP_RIGHT = 'top-right';
    public const POSITION_BOTTOM_LEFT = 'bottom-left';
    public const POSITION_BOTTOM_RIGHT = 'bottom-right';

    /**
     * Get label ID
     *
     * @return int|null
     */
    public function getLabelId(): ?int;

    /**
     * Set label ID
     *
     * @param int $labelId
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setLabelId(int $labelId): LabelInterface;

    /**
     * Get stable label code
     *
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * Set stable label code
     *
     * @param string $code
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setCode(string $code): LabelInterface;

    /**
     * Get admin-facing name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set admin-facing name
     *
     * @param string $name
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setName(string $name): LabelInterface;

    /**
     * Get active flag
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Set active flag
     *
     * @param bool $isActive
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setIsActive(bool $isActive): LabelInterface;

    /**
     * Get render priority (lower renders first)
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set render priority
     *
     * @param int $priority
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setPriority(int $priority): LabelInterface;

    /**
     * Get default storefront text
     *
     * @return string|null
     */
    public function getLabelText(): ?string;

    /**
     * Set default storefront text
     *
     * @param string $labelText
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setLabelText(string $labelText): LabelInterface;

    /**
     * Get badge text color (hex, e.g. #FFFFFF)
     *
     * @return string
     */
    public function getTextColor(): string;

    /**
     * Set badge text color
     *
     * @param string $textColor
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setTextColor(string $textColor): LabelInterface;

    /**
     * Get badge background color (hex)
     *
     * @return string
     */
    public function getBackgroundColor(): string;

    /**
     * Set badge background color
     *
     * @param string $backgroundColor
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setBackgroundColor(string $backgroundColor): LabelInterface;

    /**
     * Get badge position (one of the POSITION_* constants)
     *
     * @return string
     */
    public function getPosition(): string;

    /**
     * Set badge position
     *
     * @param string $position
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setPosition(string $position): LabelInterface;

    /**
     * Whether the label renders on product listings
     *
     * @return bool
     */
    public function isShowOnPlp(): bool;

    /**
     * Set listing visibility
     *
     * @param bool $showOnPlp
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setShowOnPlp(bool $showOnPlp): LabelInterface;

    /**
     * Whether the label renders on product pages
     *
     * @return bool
     */
    public function isShowOnPdp(): bool;

    /**
     * Set product page visibility
     *
     * @param bool $showOnPdp
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setShowOnPdp(bool $showOnPdp): LabelInterface;

    /**
     * Get computed rule type (one of the RULE_TYPE_* constants)
     *
     * @return string
     */
    public function getRuleType(): string;

    /**
     * Set computed rule type
     *
     * @param string $ruleType
     * @return \Magendoo\ProductLabels\Api\Data\LabelInterface
     */
    public function setRuleType(string $ruleType): LabelInterface;

    /**
     * Get creation timestamp (UTC)
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get last update timestamp (UTC)
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}

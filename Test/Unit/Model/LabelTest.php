<?php
/**
 * Magendoo ProductLabels - Label model unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Test\Unit\Model;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Model\Label;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Label::class)]
class LabelTest extends TestCase
{
    /**
     * @var Label
     */
    private Label $label;

    protected function setUp(): void
    {
        $this->label = (new ObjectManagerHelper($this))->getObject(Label::class);
        // Without a real resource model the ID field name never leaves its 'id' default.
        $this->label->setIdFieldName(LabelInterface::LABEL_ID);
    }

    public function testGetIdentitiesUsesCacheTagAndId(): void
    {
        $this->label->setData(LabelInterface::LABEL_ID, 7);
        $this->assertSame(['magendoo_pl_l_7'], $this->label->getIdentities());
    }

    public function testStoreOverridesParseSkipsEmptyAndMalformedRows(): void
    {
        $this->label->setData(Label::DATA_STORE_OVERRIDES, [
            ['store_id' => '1', 'label_text' => 'Reducere'],
            ['store_id' => '2', 'label_text' => ''],          // empty text -> ignored
            ['label_text' => 'no store id'],                  // malformed -> ignored
            ['store_id' => '3', 'label_text' => 'Soldes'],
        ]);
        $this->assertSame([1 => 'Reducere', 3 => 'Soldes'], $this->label->getStoreOverrides());
    }

    public function testGetTextForStoreFallsBackToDefaultText(): void
    {
        $this->label->setData(LabelInterface::LABEL_TEXT, 'Sale');
        $this->label->setData(Label::DATA_STORE_OVERRIDES, [
            ['store_id' => '2', 'label_text' => 'Soldes'],
        ]);
        $this->assertSame('Soldes', $this->label->getTextForStore(2));
        $this->assertSame('Sale', $this->label->getTextForStore(1));
    }

    public function testAvailableSetsMatchInterfaceConstants(): void
    {
        $this->assertSame(
            [LabelInterface::RULE_TYPE_NONE, LabelInterface::RULE_TYPE_IS_NEW, LabelInterface::RULE_TYPE_ON_SALE],
            Label::getAvailableRuleTypes()
        );
        $this->assertCount(4, Label::getAvailablePositions());
        $this->assertContains(LabelInterface::POSITION_TOP_LEFT, Label::getAvailablePositions());
    }

    public function testBooleanAccessorsCastStoredInts(): void
    {
        $this->label->setIsActive(true);
        $this->label->setShowOnPlp(false);
        $this->label->setShowOnPdp(true);
        $this->assertSame(1, $this->label->getData(LabelInterface::IS_ACTIVE));
        $this->assertTrue($this->label->isActive());
        $this->assertFalse($this->label->isShowOnPlp());
        $this->assertTrue($this->label->isShowOnPdp());
    }
}

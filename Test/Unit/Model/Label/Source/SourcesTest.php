<?php
/**
 * Magendoo ProductLabels - option source unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Test\Unit\Model\Label\Source;

use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\Label\Source\Position;
use Magendoo\ProductLabels\Model\Label\Source\RuleType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Position::class)]
#[CoversClass(RuleType::class)]
class SourcesTest extends TestCase
{
    public function testPositionOptionsCoverExactlyTheModelPositions(): void
    {
        $values = array_column((new Position())->toOptionArray(), 'value');
        $this->assertSame(Label::getAvailablePositions(), $values);
    }

    public function testRuleTypeOptionsCoverExactlyTheModelRuleTypes(): void
    {
        $values = array_column((new RuleType())->toOptionArray(), 'value');
        $this->assertSame(Label::getAvailableRuleTypes(), $values);
    }
}

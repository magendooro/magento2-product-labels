<?php
/**
 * Magendoo ProductLabels - LabelRepository unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Test\Unit\Model;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Api\Data\LabelSearchResultsInterfaceFactory;
use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\LabelFactory;
use Magendoo\ProductLabels\Model\LabelRepository;
use Magendoo\ProductLabels\Model\ResourceModel\Label as LabelResource;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(LabelRepository::class)]
class LabelRepositoryTest extends TestCase
{
    private LabelResource|MockObject $resource;
    private LabelFactory|MockObject $labelFactory;
    private IndexerRegistry|MockObject $indexerRegistry;
    private IndexerInterface|MockObject $indexer;
    private LabelRepository $repository;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(LabelResource::class);
        $this->labelFactory = $this->getMockBuilder(LabelFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->indexerRegistry = $this->createMock(IndexerRegistry::class);
        $this->indexer = $this->createMock(IndexerInterface::class);
        $this->repository = new LabelRepository(
            $this->resource,
            $this->labelFactory,
            $this->getMockBuilder(CollectionFactory::class)
                ->disableOriginalConstructor()->onlyMethods(['create'])->getMock(),
            $this->getMockBuilder(LabelSearchResultsInterfaceFactory::class)
                ->disableOriginalConstructor()->onlyMethods(['create'])->getMock(),
            $this->createMock(CollectionProcessorInterface::class),
            $this->indexerRegistry
        );
    }

    private function newLabel(array $data = []): Label
    {
        $label = (new ObjectManagerHelper($this))->getObject(Label::class);
        // Without a real resource model the ID field name never leaves its 'id' default.
        $label->setIdFieldName(LabelInterface::LABEL_ID);
        $label->setData($data);
        return $label;
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $label = $this->newLabel();
        $this->labelFactory->method('create')->willReturn($label);
        $this->resource->method('load')->willReturnSelf();
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById(999);
    }

    public function testSaveWrapsResourceErrors(): void
    {
        $label = $this->newLabel([LabelInterface::RULE_TYPE => 'none']);
        $this->resource->method('save')->willThrowException(new \RuntimeException('db down'));
        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($label);
    }

    #[DataProvider('invalidationProvider')]
    public function testSaveInvalidatesIndexerOnlyForComputedRuleChanges(
        array $data,
        ?array $origData,
        bool $expectInvalidate
    ): void {
        $label = $this->newLabel($data);
        if ($origData !== null) {
            $label->setOrigData(null, null);
            foreach ($origData as $key => $value) {
                $label->setOrigData($key, $value);
            }
        } else {
            $label->isObjectNew(true);
        }
        $this->resource->method('save')->willReturnSelf();
        if ($expectInvalidate) {
            $this->indexerRegistry->expects($this->once())->method('get')
                ->with('magendoo_product_labels')->willReturn($this->indexer);
            $this->indexer->expects($this->once())->method('invalidate');
        } else {
            $this->indexerRegistry->expects($this->never())->method('get');
        }
        $this->repository->save($label);
    }

    public static function invalidationProvider(): array
    {
        return [
            'new_manual_label_no_invalidate' => [
                [LabelInterface::RULE_TYPE => 'none', LabelInterface::IS_ACTIVE => 1],
                null,
                false,
            ],
            'new_computed_label_invalidates' => [
                [LabelInterface::RULE_TYPE => 'on_sale', LabelInterface::IS_ACTIVE => 1],
                null,
                true,
            ],
            'rule_type_change_invalidates' => [
                [LabelInterface::RULE_TYPE => 'is_new', LabelInterface::IS_ACTIVE => 1, LabelInterface::LABEL_ID => 4],
                [LabelInterface::RULE_TYPE => 'none', LabelInterface::IS_ACTIVE => 1],
                true,
            ],
            'display_only_edit_no_invalidate' => [
                [
                    LabelInterface::RULE_TYPE => 'on_sale',
                    LabelInterface::IS_ACTIVE => 1,
                    LabelInterface::LABEL_ID => 4,
                    LabelInterface::LABEL_TEXT => 'New text',
                ],
                [LabelInterface::RULE_TYPE => 'on_sale', LabelInterface::IS_ACTIVE => 1],
                false,
            ],
            'deactivating_computed_label_invalidates' => [
                [LabelInterface::RULE_TYPE => 'on_sale', LabelInterface::IS_ACTIVE => 0, LabelInterface::LABEL_ID => 4],
                [LabelInterface::RULE_TYPE => 'on_sale', LabelInterface::IS_ACTIVE => 1],
                true,
            ],
            'deactivating_manual_label_no_invalidate' => [
                [LabelInterface::RULE_TYPE => 'none', LabelInterface::IS_ACTIVE => 0, LabelInterface::LABEL_ID => 4],
                [LabelInterface::RULE_TYPE => 'none', LabelInterface::IS_ACTIVE => 1],
                false,
            ],
        ];
    }

    public function testDeleteComputedLabelInvalidatesIndexer(): void
    {
        $label = $this->newLabel([LabelInterface::RULE_TYPE => 'is_new']);
        $this->resource->method('delete')->willReturnSelf();
        $this->indexerRegistry->expects($this->once())->method('get')->willReturn($this->indexer);
        $this->indexer->expects($this->once())->method('invalidate');
        $this->assertTrue($this->repository->delete($label));
    }
}

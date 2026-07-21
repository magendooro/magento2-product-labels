<?php
/**
 * Magendoo ProductLabels - Label form data provider
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Ui\DataProvider\Form;

use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\ResourceModel\Label\Collection;
use Magendoo\ProductLabels\Model\ResourceModel\Label\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\ModifierPoolDataProvider;

class LabelDataProvider extends ModifierPoolDataProvider
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param ResourceConnection $resourceConnection
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $pool
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly ResourceConnection $resourceConnection,
        array $meta = [],
        array $data = [],
        ?PoolInterface $pool = null
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data, $pool);
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $overridesByLabel = $this->loadOverrides();
        /** @var Label $label */
        foreach ($this->collection->getItems() as $label) {
            $data = $label->getData();
            $data[Label::DATA_STORE_OVERRIDES] = $overridesByLabel[(int)$label->getId()] ?? [];
            $this->loadedData[$label->getId()] = $data;
        }

        $persisted = $this->dataPersistor->get('magendoo_productlabels_label');
        if (!empty($persisted)) {
            $label = $this->collection->getNewEmptyItem();
            $label->setData($persisted);
            $this->loadedData[$label->getId()] = $label->getData();
            $this->dataPersistor->clear('magendoo_productlabels_label');
        }

        return $this->loadedData;
    }

    /**
     * Store-view text overrides for all labels, keyed by label_id
     *
     * @return array<int, array<int, array{store_id: string, label_text: string}>>
     */
    private function loadOverrides(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('magendoo_product_label_store'),
                ['label_id', 'store_id', 'label_text']
            )
            ->order(['label_id ASC', 'store_id ASC']);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['label_id']][] = [
                'store_id' => (string)$row['store_id'],
                'label_text' => (string)$row['label_text'],
            ];
        }
        return $result;
    }
}

<?php
/**
 * Magendoo ProductLabels - Label resource model
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Model\ResourceModel;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Model\Label as LabelModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Persists the label row plus its store-view text overrides
 * (magendoo_product_label_store) in the same save transaction.
 */
class Label extends AbstractDb
{
    private const OVERRIDE_TABLE = 'magendoo_product_label_store';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('magendoo_product_label', 'label_id');
    }

    /**
     * Validate field values before persisting
     *
     * @param AbstractModel $object
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $code = (string)$object->getData(LabelInterface::CODE);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $code)) {
            throw new LocalizedException(
                __('Label code must contain only lowercase letters, digits, "_" or "-" (max 64 chars).')
            );
        }
        if (trim((string)$object->getData(LabelInterface::NAME)) === '') {
            throw new LocalizedException(__('Label name is required.'));
        }
        if (trim((string)$object->getData(LabelInterface::LABEL_TEXT)) === '') {
            throw new LocalizedException(__('Label text is required.'));
        }
        if (!in_array($object->getData(LabelInterface::POSITION), LabelModel::getAvailablePositions(), true)) {
            throw new LocalizedException(__('Invalid label position.'));
        }
        if (!in_array($object->getData(LabelInterface::RULE_TYPE), LabelModel::getAvailableRuleTypes(), true)) {
            throw new LocalizedException(__('Invalid label rule type.'));
        }
        foreach ([LabelInterface::TEXT_COLOR, LabelInterface::BACKGROUND_COLOR] as $colorField) {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$object->getData($colorField))) {
                throw new LocalizedException(__('Colors must be 6-digit hex values, e.g. #E02B27.'));
            }
        }
        if ($this->codeExists($code, (int)$object->getId())) {
            throw new LocalizedException(__('A label with code "%1" already exists.', $code));
        }
        return parent::_beforeSave($object);
    }

    /**
     * Persist store-view text overrides when they were provided
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        $overrides = $object->getData(LabelModel::DATA_STORE_OVERRIDES);
        if (is_array($overrides)) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::OVERRIDE_TABLE);
            $labelId = (int)$object->getId();
            $connection->delete($table, ['label_id = ?' => $labelId]);
            $rows = [];
            foreach ($overrides as $row) {
                $storeId = (int)($row['store_id'] ?? 0);
                $text = trim((string)($row['label_text'] ?? ''));
                if ($storeId > 0 && $text !== '') {
                    $rows[$storeId] = ['label_id' => $labelId, 'store_id' => $storeId, 'label_text' => $text];
                }
            }
            if ($rows) {
                $connection->insertMultiple($table, array_values($rows));
            }
        }
        return parent::_afterSave($object);
    }

    /**
     * Load store-view text overrides onto the model
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object)
    {
        if ($object->getId()) {
            $connection = $this->getConnection();
            $select = $connection->select()
                ->from($this->getTable(self::OVERRIDE_TABLE), ['store_id', 'label_text'])
                ->where('label_id = ?', (int)$object->getId())
                ->order('store_id ASC');
            $object->setData(LabelModel::DATA_STORE_OVERRIDES, $connection->fetchAll($select));
        }
        return parent::_afterLoad($object);
    }

    /**
     * Load a label by its stable code
     *
     * @param AbstractModel $object
     * @param string $code
     * @return $this
     */
    public function loadByCode(AbstractModel $object, string $code)
    {
        return $this->load($object, $code, LabelInterface::CODE);
    }

    /**
     * Whether another label already uses this code
     *
     * @param string $code
     * @param int $excludeId
     * @return bool
     */
    private function codeExists(string $code, int $excludeId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['label_id'])
            ->where('code = ?', $code);
        if ($excludeId > 0) {
            $select->where('label_id != ?', $excludeId);
        }
        return (bool)$connection->fetchOne($select);
    }
}

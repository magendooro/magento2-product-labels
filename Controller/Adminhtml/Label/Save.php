<?php
/**
 * Magendoo ProductLabels - Label save controller
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Controller\Adminhtml\Label;

use Magendoo\ProductLabels\Api\Data\LabelInterface;
use Magendoo\ProductLabels\Api\LabelRepositoryInterface;
use Magendoo\ProductLabels\Model\Label;
use Magendoo\ProductLabels\Model\LabelFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magendoo_ProductLabels::labels';

    /**
     * @param Context $context
     * @param LabelRepositoryInterface $labelRepository
     * @param LabelFactory $labelFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly LabelFactory $labelFactory,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * Persist a label from form data
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        $data = $request->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $labelId = (int)($data[LabelInterface::LABEL_ID] ?? 0);
        try {
            if ($labelId) {
                /** @var Label $label */
                $label = $this->labelRepository->getById($labelId);
            } else {
                $label = $this->labelFactory->create();
            }

            $label->setCode(trim((string)($data[LabelInterface::CODE] ?? '')));
            $label->setName(trim((string)($data[LabelInterface::NAME] ?? '')));
            $label->setIsActive(!empty($data[LabelInterface::IS_ACTIVE]));
            $label->setPriority((int)($data[LabelInterface::PRIORITY] ?? 0));
            $label->setLabelText(trim((string)($data[LabelInterface::LABEL_TEXT] ?? '')));
            $label->setTextColor(strtoupper(trim((string)($data[LabelInterface::TEXT_COLOR] ?? '#FFFFFF'))));
            $label->setBackgroundColor(
                strtoupper(trim((string)($data[LabelInterface::BACKGROUND_COLOR] ?? '#E02B27')))
            );
            $label->setPosition((string)($data[LabelInterface::POSITION] ?? LabelInterface::POSITION_TOP_LEFT));
            $label->setShowOnPlp(!empty($data[LabelInterface::SHOW_ON_PLP]));
            $label->setShowOnPdp(!empty($data[LabelInterface::SHOW_ON_PDP]));
            $label->setRuleType((string)($data[LabelInterface::RULE_TYPE] ?? LabelInterface::RULE_TYPE_NONE));
            $label->setData(
                Label::DATA_STORE_OVERRIDES,
                is_array($data[Label::DATA_STORE_OVERRIDES] ?? null) ? $data[Label::DATA_STORE_OVERRIDES] : []
            );

            $label = $this->labelRepository->save($label);
            $this->messageManager->addSuccessMessage(__('The label has been saved.'));
            $this->dataPersistor->clear('magendoo_productlabels_label');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['label_id' => $label->getLabelId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the label.'));
        }

        $this->dataPersistor->set('magendoo_productlabels_label', $data);
        if ($labelId) {
            return $resultRedirect->setPath('*/*/edit', ['label_id' => $labelId]);
        }
        return $resultRedirect->setPath('*/*/new');
    }
}

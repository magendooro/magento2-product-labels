<?php
/**
 * Magendoo ProductLabels - Label delete controller
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Controller\Adminhtml\Label;

use Magendoo\ProductLabels\Api\LabelRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magendoo_ProductLabels::labels';

    /**
     * @param Context $context
     * @param LabelRepositoryInterface $labelRepository
     */
    public function __construct(
        Context $context,
        private readonly LabelRepositoryInterface $labelRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Delete a label by ID
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $labelId = (int)$this->getRequest()->getParam('label_id');
        if (!$labelId) {
            $this->messageManager->addErrorMessage(__('We can\'t find a label to delete.'));
            return $resultRedirect->setPath('*/*/');
        }
        try {
            $this->labelRepository->deleteById($labelId);
            $this->messageManager->addSuccessMessage(__('The label has been deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This label no longer exists.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the label.'));
        }
        return $resultRedirect->setPath('*/*/');
    }
}

<?php
/**
 * Magendoo ProductLabels - Label edit controller
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Controller\Adminhtml\Label;

use Magendoo\ProductLabels\Api\LabelRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Edit extends Action implements HttpGetActionInterface
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
     * Render the label edit form (new or existing)
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $labelId = (int)$this->getRequest()->getParam('label_id');
        $title = __('New Label');
        if ($labelId) {
            try {
                $label = $this->labelRepository->getById($labelId);
                $title = __('Edit Label "%1"', $label->getName());
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This label no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                return $resultRedirect->setPath('*/*/');
            }
        }
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magendoo_ProductLabels::labels');
        $resultPage->getConfig()->getTitle()->prepend(__('Product Labels'));
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}

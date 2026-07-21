<?php
/**
 * Magendoo ProductLabels - Shared edit-form button plumbing
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */

declare(strict_types=1);

namespace Magendoo\ProductLabels\Block\Adminhtml\Label\Edit;

use Magento\Backend\Block\Widget\Context;

abstract class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(
        protected readonly Context $context
    ) {
    }

    /**
     * Current label ID from the request (0 when creating)
     *
     * @return int
     */
    public function getLabelId(): int
    {
        return (int)$this->context->getRequest()->getParam('label_id');
    }

    /**
     * Build a URL
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}

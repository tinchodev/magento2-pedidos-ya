<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Block\Adminhtml\ProductsProfile\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly Registry $coreRegistry
    ) {
    }

    public function getProfileId(): ?int
    {
        $model = $this->coreRegistry->registry('nx6_pedidosya_products_profile');

        return $model ? (int) $model->getId() ?: null : null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}

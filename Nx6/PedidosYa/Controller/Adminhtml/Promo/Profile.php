<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;

abstract class Profile extends Action
{
    public const ADMIN_RESOURCE = 'Nx6_PedidosYa::promo_profile';

    protected function initPage(Page $resultPage): Page
    {
        $resultPage->setActiveMenu('Nx6_PedidosYa::promo_profile')
            ->addBreadcrumb(__('PedidosYa'), __('PedidosYa'))
            ->addBreadcrumb(__('Promo Profiles'), __('Promo Profiles'));

        return $resultPage;
    }
}

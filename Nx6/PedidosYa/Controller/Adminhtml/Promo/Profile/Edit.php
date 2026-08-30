<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;
use Nx6\PedidosYa\Model\PromoProfileFactory;

class Edit extends Profile implements HttpGetActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $coreRegistry,
        private readonly PromoProfileFactory $promoProfileFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->promoProfileFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This promo profile no longer exists.'));

                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('nx6_pedidosya_promo_profile', $model);

        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage);
        $resultPage->addBreadcrumb(
            $id ? __('Edit Promo Profile') : __('New Promo Profile'),
            $id ? __('Edit Promo Profile') : __('New Promo Profile')
        );
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? __('Edit Promo Profile #%1', $id) : __('New Promo Profile')
        );

        return $resultPage;
    }
}

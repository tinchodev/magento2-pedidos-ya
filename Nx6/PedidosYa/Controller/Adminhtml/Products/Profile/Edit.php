<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;
use Nx6\PedidosYa\Model\ProductsProfileFactory;

class Edit extends Profile implements HttpGetActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $coreRegistry,
        private readonly ProductsProfileFactory $productsProfileFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->productsProfileFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This products profile no longer exists.'));

                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('nx6_pedidosya_products_profile', $model);

        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage);
        $resultPage->addBreadcrumb(
            $id ? __('Edit Products Profile') : __('New Products Profile'),
            $id ? __('Edit Products Profile') : __('New Products Profile')
        );
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? __('Edit Products Profile #%1', $id) : __('New Products Profile')
        );

        return $resultPage;
    }
}

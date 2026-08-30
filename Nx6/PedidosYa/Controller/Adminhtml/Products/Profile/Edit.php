<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;

use Magento\Backend\App\Action\Context;
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
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $coreRegistry,
        private readonly ProductsProfileFactory $productsProfileFactory
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        $productsProfile = $this->productsProfileFactory->create();

        if ($id !== 0) {
            $productsProfile->load($id);
            if (!$productsProfile->getId()) {
                $this->messageManager->addErrorMessage(__('This products profile no longer exists.'));

                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('nx6_pedidosya_products_profile', $productsProfile);

        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage);
        $resultPage->addBreadcrumb(
            $id !== 0 ? __('Edit Products Profile') : __('New Products Profile'),
            $id !== 0 ? __('Edit Products Profile') : __('New Products Profile')
        );
        $resultPage->getConfig()->getTitle()->prepend(
            $id !== 0 ? __('Edit Products Profile #%1', $id) : __('New Products Profile')
        );

        return $resultPage;
    }
}

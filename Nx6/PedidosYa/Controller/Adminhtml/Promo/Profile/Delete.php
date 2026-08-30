<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;
use Nx6\PedidosYa\Model\PromoProfileFactory;

class Delete extends Profile implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly PromoProfileFactory $promoProfileFactory
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');

        if ($id === 0) {
            $this->messageManager->addErrorMessage(__("We can't find a promo profile to delete."));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $model = $this->promoProfileFactory->create();
            $model->load($id);
            $model->delete();

            $this->messageManager->addSuccessMessage(__('You deleted the promo profile.'));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}

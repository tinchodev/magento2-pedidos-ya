<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;
use Nx6\PedidosYa\Model\Export\ExportRunner;
use Nx6\PedidosYa\Model\PromoProfileFactory;

class Run extends Profile implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly PromoProfileFactory $promoProfileFactory,
        private readonly ExportRunner $exportRunner
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');
        $promoProfile = $this->promoProfileFactory->create();
        $promoProfile->load($id);

        if (!$promoProfile->getId()) {
            $this->messageManager->addErrorMessage(__('This promo profile no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $result = $this->exportRunner->run($promoProfile);
            $this->messageManager->addSuccessMessage(
                __('Export ran successfully: %1', $result)
            );
        } catch (\Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Export failed: %1', $throwable->getMessage()));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }
}

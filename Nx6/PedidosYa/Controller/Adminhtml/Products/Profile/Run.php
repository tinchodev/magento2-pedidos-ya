<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;
use Nx6\PedidosYa\Model\Export\ExportRunner;
use Nx6\PedidosYa\Model\ProductsProfileFactory;

class Run extends Profile implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly ProductsProfileFactory $productsProfileFactory,
        private readonly ExportRunner $exportRunner
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');
        $productsProfile = $this->productsProfileFactory->create();
        $productsProfile->load($id);

        if (!$productsProfile->getId()) {
            $this->messageManager->addErrorMessage(__('This products profile no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $result = $this->exportRunner->run($productsProfile);
            $this->messageManager->addSuccessMessage(
                __('Export ran successfully: %1', $result)
            );
        } catch (\Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Export failed: %1', $throwable->getMessage()));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }
}

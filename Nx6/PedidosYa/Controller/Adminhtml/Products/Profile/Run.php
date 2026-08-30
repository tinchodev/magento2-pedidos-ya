<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;
use Nx6\PedidosYa\Model\Export\ExportRunner;
use Nx6\PedidosYa\Model\ProductsProfileFactory;

class Run extends Profile implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly ProductsProfileFactory $productsProfileFactory,
        private readonly ExportRunner $exportRunner
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->productsProfileFactory->create();
        $model->load($id);

        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('This products profile no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $result = $this->exportRunner->run($model);
            $this->messageManager->addSuccessMessage(
                __('Export ran successfully: %1', $result)
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Export failed: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }
}

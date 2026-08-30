<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;
use Nx6\PedidosYa\Model\ProductsProfileFactory;
use Nx6\PedidosYa\Model\Sftp\Client;
use Nx6\PedidosYa\Model\Sftp\CredentialsBuilder;

class TestConnection extends Profile implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly ProductsProfileFactory $productsProfileFactory,
        private readonly CredentialsBuilder $credentialsBuilder,
        private readonly Client $sftpClient
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
            $this->sftpClient->testConnection($this->credentialsBuilder->fromProfile($model));
            $this->messageManager->addSuccessMessage(__('Connection successful.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Connection failed: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }
}

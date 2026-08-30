<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Nx6\PedidosYa\Controller\Adminhtml\Products\Profile;
use Nx6\PedidosYa\Model\ExportColumns;
use Nx6\PedidosYa\Model\ProductsProfileFactory;

class Save extends Profile implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly ProductsProfileFactory $productsProfileFactory,
        private readonly ExportColumns $exportColumns,
        private readonly EncryptorInterface $encryptor,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        // The form posts the primary field (products_profile_id) in the body; the submit URL carries no
        // "id" route param, so reading only getParam('id') turns every edit into an insert.
        $id = (int) ($data['products_profile_id'] ?? $this->getRequest()->getParam('id'));
        $model = $this->productsProfileFactory->create();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This products profile no longer exists.'));

                return $resultRedirect->setPath('*/*/');
            }
        }

        $model->setData('name', trim((string) ($data['name'] ?? '')) ?: null);
        $model->setStoreId((int) ($data['store_id'] ?? 0));
        $model->setVendorId(trim((string) ($data['vendor_id'] ?? '')));
        $model->setFilePrefix(trim((string) ($data['file_prefix'] ?? '')) ?: 'products');
        $model->setIsActive(!empty($data['is_active']) ? 1 : 0);
        $model->setData('excluded_skus', trim((string) ($data['excluded_skus'] ?? '')) ?: null);
        $model->setData('only_enabled', !empty($data['only_enabled']) ? 1 : 0);
        $model->setData('markup_percent', $this->toNullableFloat($data['markup_percent'] ?? null));
        $model->setData('max_price_enabled', !empty($data['max_price_enabled']) ? 1 : 0);
        $model->setData('max_price', $this->toNullableFloat($data['max_price'] ?? null));
        $this->applyMapping($model, $data);
        $this->applySftp($model, $data);

        try {
            $model->save();
            $this->messageManager->addSuccessMessage(__('You saved the products profile.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving the products profile.')
            );
        }

        $this->dataPersistor->set('nx6_pedidosya_products_profile', $data);

        return $resultRedirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }

    private function applyMapping(\Nx6\PedidosYa\Model\ProductsProfile $model, array $data): void
    {
        foreach (array_keys($this->exportColumns->getProductsColumns()) as $column) {
            $source = trim((string) ($data[$column . '_source'] ?? ''));

            if (!$this->exportColumns->isStockMappable($column) && str_starts_with($source, 'stock:')) {
                $source = '';
            }

            $model->setData($column . '_source', $source ?: null);

            $default = trim((string) ($data[$column . '_default'] ?? ''));
            $model->setData($column . '_default', $default !== '' ? $default : null);
        }
    }

    /**
     * The password field is never sent back to the browser (see DataProvider::getData()), so an
     * empty post here means "leave the stored password as is", not "clear it".
     */
    private function applySftp(\Nx6\PedidosYa\Model\ProductsProfile $model, array $data): void
    {
        $model->setData('sftp_host', trim((string) ($data['sftp_host'] ?? '')) ?: null);
        $model->setData('sftp_port', (int) ($data['sftp_port'] ?? 0) ?: null);
        $model->setData('sftp_username', trim((string) ($data['sftp_username'] ?? '')) ?: null);
        $model->setData('sftp_remote_path', trim((string) ($data['sftp_remote_path'] ?? '')) ?: null);
        $model->setData('sftp_timeout', (int) ($data['sftp_timeout'] ?? 0) ?: null);

        $password = (string) ($data['sftp_password'] ?? '');
        if ($password !== '') {
            $model->setData('sftp_password', $this->encryptor->encrypt($password));
        }
    }

    private function toNullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}

<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\Filter\DateTime as DateTimeFilter;
use Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;
use Nx6\PedidosYa\Model\ExportColumns;
use Nx6\PedidosYa\Model\PromoProfile;
use Nx6\PedidosYa\Model\PromoProfileFactory;

class Save extends Profile implements HttpPostActionInterface
{
    /**
     * Columns whose "default" value is posted by a datepicker and needs converting from the
     * picker's ISO datetime into the plain "Y-m-d H:i:s" string the CSV expects.
     */
    private const array DATE_COLUMNS = ['start_date', 'end_date'];

    public function __construct(
        Context $context,
        private readonly PromoProfileFactory $promoProfileFactory,
        private readonly ExportColumns $exportColumns,
        private readonly EncryptorInterface $encryptor,
        private readonly DateTimeFilter $dateTimeFilter,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        // The form posts the primary field (promo_profile_id) in the body; the submit URL carries no
        // "id" route param, so reading only getParam('id') turns every edit into an insert.
        $id = (int) ($data['promo_profile_id'] ?? $this->getRequest()->getParam('id'));
        $promoProfile = $this->promoProfileFactory->create();
        if ($id !== 0) {
            $promoProfile->load($id);
            if (!$promoProfile->getId()) {
                $this->messageManager->addErrorMessage(__('This promo profile no longer exists.'));

                return $resultRedirect->setPath('*/*/');
            }
        }

        $promoProfile->setData('name', trim((string) ($data['name'] ?? '')) ?: null);
        $promoProfile->setStoreId((int) ($data['store_id'] ?? 0));
        $promoProfile->setVendorId(trim((string) ($data['vendor_id'] ?? '')));
        $promoProfile->setFilePrefix(trim((string) ($data['file_prefix'] ?? '')) ?: 'promo');
        $promoProfile->setIsActive(empty($data['is_active']) ? 0 : 1);
        $promoProfile->setData('use_all_enabled', empty($data['use_all_enabled']) ? 0 : 1);
        $promoProfile->setData('included_skus', trim((string) ($data['included_skus'] ?? '')) ?: null);
        $promoProfile->setData('markdown_percent', $this->toNullableFloat($data['markdown_percent'] ?? null));
        $this->applyMapping($promoProfile, $data);
        $this->applySftp($promoProfile, $data);

        try {
            $promoProfile->save();
            $this->messageManager->addSuccessMessage(__('You saved the promo profile.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $promoProfile->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving the promo profile.')
            );
        }

        $this->dataPersistor->set('nx6_pedidosya_promo_profile', $data);

        return $resultRedirect->setPath('*/*/edit', $id !== 0 ? ['id' => $id] : []);
    }

    private function applyMapping(PromoProfile $promoProfile, array $data): void
    {
        foreach (array_keys($this->exportColumns->getPromoColumns()) as $column) {
            $source = trim((string) ($data[$column . '_source'] ?? ''));

            if (!$this->exportColumns->isStockMappable($column) && str_starts_with($source, 'stock:')) {
                $source = '';
            }

            $promoProfile->setData($column . '_source', $source ?: null);
            $promoProfile->setData($column . '_default', $this->resolveDefaultValue($column, $data));
        }
    }

    private function resolveDefaultValue(string $column, array $data): ?string
    {
        $raw = trim((string) ($data[$column . '_default'] ?? ''));
        if ($raw === '') {
            return null;
        }

        if (!in_array($column, self::DATE_COLUMNS, true)) {
            return $raw;
        }

        // The datepicker posts an ISO datetime (e.g. "2025-07-01T00:00:00.000Z"); convert it to
        // the plain "Y-m-d H:i:s" string the exported CSV expects.
        return $this->dateTimeFilter->filter($raw);
    }

    /**
     * The password field is never sent back to the browser (see DataProvider::getData()), so an
     * empty post here means "leave the stored password as is", not "clear it".
     */
    private function applySftp(PromoProfile $promoProfile, array $data): void
    {
        $promoProfile->setData('sftp_host', trim((string) ($data['sftp_host'] ?? '')) ?: null);
        $promoProfile->setData('sftp_port', (int) ($data['sftp_port'] ?? 0) ?: null);
        $promoProfile->setData('sftp_username', trim((string) ($data['sftp_username'] ?? '')) ?: null);
        $promoProfile->setData('sftp_remote_path', trim((string) ($data['sftp_remote_path'] ?? '')) ?: null);
        $promoProfile->setData('sftp_timeout', (int) ($data['sftp_timeout'] ?? 0) ?: null);

        $password = (string) ($data['sftp_password'] ?? '');
        if ($password !== '') {
            $promoProfile->setData('sftp_password', $this->encryptor->encrypt($password));
        }
    }

    private function toNullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}

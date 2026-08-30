<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\PromoProfile;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Registry;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Nx6\PedidosYa\Model\ResourceModel\PromoProfile\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly Registry $coreRegistry,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly PoolInterface $pool,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * The "mapping" fieldset is empty in the form XML; its rows are injected by the
     * modifier pool from ExportColumns. See Nx6\PedidosYa\Ui\Component\Form\MappingModifier.
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();

        foreach ($this->pool->getModifiersInstances() as $modifier) {
            $meta = $modifier->modifyMeta($meta);
        }

        return $meta;
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];

        /** @var \Nx6\PedidosYa\Model\PromoProfile $model */
        $model = $this->coreRegistry->registry('nx6_pedidosya_promo_profile');

        if ($model) {
            // Magento\Ui\Component\Form::getDataSourceData() looks up this array by the "id" request
            // param (null for a new record, which PHP treats as the '' array key when read via isset()).
            $data = $model->getData();
            // Never send the (encrypted) stored password back down to the browser; the field stays
            // blank in the UI and Save only overwrites it when a new value is actually posted.
            unset($data['sftp_password']);
            $this->loadedData[$model->getId() ?? ''] = $data;
        }

        $persisted = $this->dataPersistor->get('nx6_pedidosya_promo_profile');
        if (!empty($persisted)) {
            // Save persists the raw POST, whose primary field is promo_profile_id; the lookup key
            // above must match the "id" request param, which is '' for a new record.
            unset($persisted['sftp_password']);
            $index = $persisted['promo_profile_id'] ?? '';
            $this->loadedData[$index] = $persisted;
            $this->dataPersistor->clear('nx6_pedidosya_promo_profile');
        }

        return $this->loadedData;
    }
}

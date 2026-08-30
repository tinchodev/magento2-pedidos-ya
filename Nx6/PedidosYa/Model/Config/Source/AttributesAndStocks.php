<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;

/**
 * Source model for the "quantity" CSV column: product attributes plus MSI stocks (including
 * Default Stock) and MSI sources (individual physical locations feeding those stocks).
 */
class AttributesAndStocks extends Attributes
{
    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory,
        private readonly StockRepositoryInterface $stockRepository,
        private readonly SourceRepositoryInterface $sourceRepository
    ) {
        parent::__construct($attributeCollectionFactory);
    }

    public function toOptionArray(): array
    {
        $options = parent::toOptionArray();

        $stockOptions = [];
        foreach ($this->stockRepository->getList()->getItems() as $stock) {
            $stockOptions[] = [
                'value' => 'stock:' . $stock->getStockId(),
                'label' => sprintf('%s (#%d)', $stock->getName(), $stock->getStockId()),
            ];
        }

        if ($stockOptions) {
            $options[] = [
                'label' => __('Stocks'),
                'value' => $stockOptions,
            ];
        }

        $sourceOptions = [];
        foreach ($this->sourceRepository->getList()->getItems() as $source) {
            $sourceOptions[] = [
                'value' => 'source:' . $source->getSourceCode(),
                'label' => sprintf('%s (%s)', $source->getName(), $source->getSourceCode()),
            ];
        }

        if ($sourceOptions) {
            $options[] = [
                'label' => __('Sources'),
                'value' => $sourceOptions,
            ];
        }

        return $options;
    }
}

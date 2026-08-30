<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for the "attribute:<code>" mapping options offered on every CSV column's
 * "Source" field. Lists catalog product attributes, excluding types that can't resolve to a
 * plain CSV value on their own (image/gallery attributes).
 */
class Attributes implements OptionSourceInterface
{
    private const array EXCLUDED_FRONTEND_INPUTS = ['media_image', 'gallery'];

    public function __construct(
        private readonly AttributeCollectionFactory $attributeCollectionFactory
    ) {
    }

    #[\Override]
    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => __('-- Not Mapped --')],
        ];

        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('frontend_input', ['nin' => self::EXCLUDED_FRONTEND_INPUTS]);
        $collection->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $label = $attribute->getFrontendLabel() ?: $attribute->getAttributeCode();
            $options[] = [
                'value' => 'attribute:' . $attribute->getAttributeCode(),
                'label' => sprintf('%s (%s)', $label, $attribute->getAttributeCode()),
            ];
        }

        return $options;
    }
}

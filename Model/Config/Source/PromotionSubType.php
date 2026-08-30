<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Allowed values for the "promotion_sub_type" CSV column.
 *
 * Limited to the values actually present in sample/promotions_sample.csv (both under
 * same_item_bundle rows; strikethrough rows leave the column blank). Not independently
 * confirmed against PedidosYa's authoritative integration spec - if a real export gets
 * rejected on this column, verify against soporteintegraciones@pedidosya.com.
 */
class PromotionSubType implements OptionSourceInterface
{
    public const FREE_ITEM = 'free_item';

    public const ABSOLUTE_VALUE_OFF = 'absolute_value_off';

    #[\Override]
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- No Default --')],
            ['value' => self::FREE_ITEM, 'label' => __('Free Item')],
            ['value' => self::ABSOLUTE_VALUE_OFF, 'label' => __('Absolute Value Off')],
        ];
    }
}

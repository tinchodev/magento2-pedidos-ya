<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Allowed values for the "promotion_type" CSV column, per developer.pedidosya.com's
 * promotions SFTP format (enum, required): same_item_bundle, strikethrough.
 */
class PromotionType implements OptionSourceInterface
{
    public const SAME_ITEM_BUNDLE = 'same_item_bundle';

    public const STRIKETHROUGH = 'strikethrough';

    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- No Default --')],
            ['value' => self::SAME_ITEM_BUNDLE, 'label' => __('Same Item Bundle')],
            ['value' => self::STRIKETHROUGH, 'label' => __('Strikethrough')],
        ];
    }
}

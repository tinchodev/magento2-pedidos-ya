<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model;

/**
 * Canonical CSV column definitions for each PedidosYa export entity.
 *
 * Column order here is also the CSV column order, matching the "single store"
 * dual file format documented at developer.pedidosya.com. Each column becomes a pair of
 * "<column>_source" / "<column>_default" fields on the owning profile entity.
 */
class ExportColumns
{
    /**
     * The only column allowed to source its value from an MSI stock instead of a product attribute.
     */
    public const STOCK_MAPPABLE_COLUMN = 'quantity';

    private const array PRODUCTS_COLUMNS = [
        'sku' => 'SKU',
        'barcode' => 'Barcode',
        'price' => 'Price',
        'active' => 'Active',
        'quantity' => 'Quantity',
        'vendors' => 'Vendors',
        'exclude' => 'Exclude',
    ];

    private const array PROMO_COLUMNS = [
        'barcode' => 'Barcode',
        'sku' => 'SKU',
        'campaign_name' => 'Campaign Name',
        'reason' => 'Reason',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'promotion_type' => 'Promotion Type',
        'promotion_sub_type' => 'Promotion Sub Type',
        'discounted_price' => 'Discounted Price',
        'max_no_of_orders' => 'Max No. of Orders',
        'discount_usage_limit' => 'Discount Usage Limit',
        'bundle_details' => 'Bundle Details',
        'bundle_discount' => 'Bundle Discount',
        'campaign_status' => 'Campaign Status',
    ];

    /**
     * @return array<string, string> column code => label
     */
    public function getProductsColumns(): array
    {
        return self::PRODUCTS_COLUMNS;
    }

    /**
     * @return array<string, string> column code => label
     */
    public function getPromoColumns(): array
    {
        return self::PROMO_COLUMNS;
    }

    public function isStockMappable(string $column): bool
    {
        return $column === self::STOCK_MAPPABLE_COLUMN;
    }
}

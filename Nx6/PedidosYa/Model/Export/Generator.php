<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Export;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Nx6\PedidosYa\Helper\Config;
use Nx6\PedidosYa\Model\ExportColumns;
use Nx6\PedidosYa\Model\ProductsProfile;
use Nx6\PedidosYa\Model\PromoProfile;
use Psr\Log\LoggerInterface;

/**
 * Generates the PedidosYa CSV export file for a single export profile, streaming products
 * page by page to stay memory-safe on large catalogs.
 */
class Generator
{
    private const PAGE_SIZE = 1000;

    /**
     * PedidosYa's promotions SFTP integration rejects any file over 20,000 rows including the
     * header - so a promo batch tops out at 19,999 data rows. Products Profile exports aren't
     * covered by that limit and are never split.
     */
    private const PROMO_MAX_ROWS_PER_BATCH = 19999;

    public function __construct(
        private readonly ExportColumns $exportColumns,
        private readonly Filesystem $filesystem,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly SourceItemRepositoryInterface $sourceItemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{path: string, filename: string, rows: int}[] one entry per batch file - a
     *     Products Profile (or a Promo Profile within the row limit) always yields exactly one
     */
    public function generate(ProductsProfile|PromoProfile $profile): array
    {
        $isPromo = $profile instanceof PromoProfile;
        $columns = array_keys($isPromo ? $this->exportColumns->getPromoColumns() : $this->exportColumns->getProductsColumns());
        $storeId = (int) $profile->getStoreId();

        // Products Profile: skip these SKUs no matter what, optionally restricted to enabled
        // products only. Promo Profile: only these SKUs (or every enabled product, if "Use All
        // Enabled Products" is on) are part of the promo.
        $excludedSkus = $isPromo ? [] : $this->parseSkuList((string) $profile->getData('excluded_skus'));
        $onlyEnabled = !$isPromo && (bool) $profile->getData('only_enabled');
        $maxPriceEnabled = !$isPromo && (bool) $profile->getData('max_price_enabled');
        $maxPrice = (float) $profile->getData('max_price');
        $useAllEnabled = $isPromo && (bool) $profile->getData('use_all_enabled');
        $includedSkus = $isPromo && !$useAllEnabled ? $this->parseSkuList((string) $profile->getData('included_skus')) : [];
        // Promo, not "all enabled", and nothing listed: there's nothing to export.
        $nothingToExport = $isPromo && !$useAllEnabled && !$includedSkus;

        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $varDir->create('pedidosya/export');

        // Working filenames key off the profile ID (not the file prefix/vendor ID) since the
        // final, batch-numbered names aren't known until generation finishes - a Promo Profile
        // only turns out to need N > 1 files once all rows have been counted.
        $maxRowsPerBatch = $isPromo ? self::PROMO_MAX_ROWS_PER_BATCH : null;
        $workingPathPrefix = 'pedidosya/export/' . ($isPromo ? 'promo' : 'products') . '_' . $profile->getId() . '_batch';
        $batches = [];
        $batchNumber = 0;
        $stream = null;

        $openBatch = function () use (&$stream, &$batchNumber, &$batches, $varDir, $workingPathPrefix, $columns): void {
            $batchNumber++;
            $relativePath = $workingPathPrefix . $batchNumber . '.csv';
            $stream = $varDir->openFile($relativePath, 'w+');
            $stream->writeCsv($columns);
            $batches[] = ['relativePath' => $relativePath, 'rows' => 0];
        };

        $rowCount = 0;

        try {
            $openBatch();

            if (!$nothingToExport) {
                $attributeCodes = $this->collectAttributeCodes($profile, $columns);
                if ($isPromo && (float) $profile->getData('markdown_percent') > 0) {
                    $attributeCodes[] = 'price';
                }
                if (!$isPromo) {
                    // Name isn't a mapped CSV column, but is needed to check the product
                    // against the blacklisted-names setting below.
                    $attributeCodes[] = 'name';
                }
                $sourceCodes = $this->collectSourceCodes($profile, $columns);

                $collection = $this->productCollectionFactory->create();
                $collection->addStoreFilter($storeId);
                $collection->addAttributeToSelect(array_merge(['sku'], $attributeCodes));

                if ($useAllEnabled || $onlyEnabled) {
                    $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
                } elseif ($includedSkus) {
                    $collection->addAttributeToFilter('sku', ['in' => $includedSkus]);
                }

                $collection->setPageSize(self::PAGE_SIZE);

                $lastPage = $collection->getLastPageNumber();
                for ($page = 1; $page <= $lastPage; $page++) {
                    $collection->clear();
                    $collection->setCurPage($page);
                    $collection->setPageSize(self::PAGE_SIZE);

                    // One batched lookup per page (per source code) instead of one per product -
                    // avoids an N+1 against inventory_source_item for large catalogs.
                    $pageSkus = array_map(static fn ($product): string => (string) $product->getSku(), $collection->getItems());
                    $sourceQtyByCodeAndSku = $this->prefetchSourceQuantities($sourceCodes, $pageSkus);

                    foreach ($collection as $product) {
                        if ($excludedSkus && in_array((string) $product->getSku(), $excludedSkus, true)) {
                            continue;
                        }

                        $row = $this->buildRow($columns, $profile, $product, $storeId, $sourceQtyByCodeAndSku);

                        // Checked against the resolved (post-markup) Price actually going into
                        // the CSV, not the product's raw base price. Flagged inactive rather than
                        // left out of the export entirely, so PedidosYa still has a row for the
                        // product (e.g. to know it exists) without offering it for sale overpriced.
                        if ($maxPriceEnabled
                            && is_numeric($row['price'] ?? null)
                            && (float) $row['price'] > $maxPrice
                            && array_key_exists('active', $row)
                        ) {
                            $row['active'] = '0';
                        }

                        // Never offer a product for sale that PedidosYa would have nothing to
                        // fulfill: zero or negative stock forces Active to "0" regardless of what
                        // the mapped Active source/default resolved to.
                        if (array_key_exists('active', $row)
                            && is_numeric($row['quantity'] ?? null)
                            && (float) $row['quantity'] <= 0
                        ) {
                            $row['active'] = '0';
                        }

                        // Blacklisted products must never be sold, regardless of Magento's
                        // enabled status or available stock.
                        if (array_key_exists('active', $row)
                            && $this->config->isBlacklisted((string) $product->getName())
                        ) {
                            $row['active'] = '0';
                        }

                        if ($isPromo && ($row['discounted_price'] ?? '') === '') {
                            continue;
                        }

                        foreach ($this->expandRows($columns, $profile, $product, $row) as $expandedRow) {
                            $currentBatch = array_key_last($batches);
                            if ($maxRowsPerBatch !== null && $batches[$currentBatch]['rows'] >= $maxRowsPerBatch) {
                                $stream->close();
                                $openBatch();
                                $currentBatch = array_key_last($batches);
                            }

                            $stream->writeCsv(array_values($expandedRow));
                            $rowCount++;
                            $batches[$currentBatch]['rows']++;
                        }
                    }
                }
            }
        } finally {
            $stream?->close();
        }

        return $this->finalizeBatches($varDir, $batches, $profile, $isPromo);
    }

    /**
     * Renames each batch's working file to its public-facing name and returns the upload-ready
     * result set. A single batch keeps the plain "<prefix>_<vendor_id>.csv" name; two or more get
     * numbered "<prefix><batch_number>_<vendor_id>.csv" (e.g. promo1_290056.csv, promo2_290056.csv)
     * - the batch number is folded into the prefix so the name still matches PedidosYa's documented
     * "<prefix>_<vendor_id>.csv" single-vendor pattern.
     *
     * @param array{relativePath: string, rows: int}[] $batches
     * @return array{path: string, filename: string, rows: int}[]
     */
    private function finalizeBatches(
        WriteInterface $varDir,
        array $batches,
        ProductsProfile|PromoProfile $profile,
        bool $isPromo
    ): array {
        $isMultiBatch = $isPromo && count($batches) > 1;
        $results = [];

        foreach ($batches as $index => $batch) {
            $filename = $profile->getFilePrefix()
                . ($isMultiBatch ? (string) ($index + 1) : '')
                . '_' . $profile->getVendorId() . '.csv';
            $finalRelativePath = 'pedidosya/export/' . $filename;

            if ($batch['relativePath'] !== $finalRelativePath) {
                $varDir->renameFile($batch['relativePath'], $finalRelativePath);
            }

            $results[] = [
                'path' => $varDir->getAbsolutePath($finalRelativePath),
                'filename' => $filename,
                'rows' => $batch['rows'],
            ];
        }

        return $results;
    }

    /**
     * Public so third-party modules can plug onto it (e.g. Farma_PedidosYa applying a
     * client-specific fallback when a mapped column resolves empty).
     *
     * @param string[] $columns
     * @param \Magento\Catalog\Model\Product $product
     * @param array<string, array<string, float>> $sourceQtyByCodeAndSku
     * @return array<string, string>
     */
    public function buildRow(
        array $columns,
        ProductsProfile|PromoProfile $profile,
        $product,
        int $storeId,
        array $sourceQtyByCodeAndSku
    ): array {
        $row = [];
        foreach ($columns as $column) {
            $source = (string) $profile->getData($column . '_source');
            $default = (string) $profile->getData($column . '_default');
            $value = $this->resolveValue($source, $default, $product, $storeId, $sourceQtyByCodeAndSku);

            if ($column === 'barcode') {
                $value = $this->normalizeBarcode($value);
            }

            if ($column === 'price' && $profile instanceof ProductsProfile) {
                $value = $this->applyMarkup($value, $profile);
            }

            if ($column === 'active' && $profile instanceof ProductsProfile) {
                $value = $this->normalizeActive($value);
            }

            if ($column === 'quantity' && $profile instanceof ProductsProfile) {
                $value = $this->normalizeQuantity($value);
            }

            if ($column === 'discounted_price' && $profile instanceof PromoProfile) {
                $markdownValue = $this->applyMarkdown($profile, $product);
                if ($markdownValue !== null) {
                    $value = $markdownValue;
                }
            }

            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * Fans a single built row out into one or more CSV rows. By default a built row maps to
     * exactly one CSV row; public and non-final so third-party modules can plug onto it (e.g.
     * Farma_PedidosYa emitting one row per barcode when the Barcode column is mapped to a
     * multi-value product attribute).
     *
     * Called after all of {@see buildRow}'s post-processing (markup, Active/Quantity/barcode
     * normalization, the Discounted Price fallback plugin) has run, so each returned row is
     * already export-ready and every expansion shares the same resolved values. Row-count and
     * batch-size limits (the promo 19,999-row split) are applied per returned row.
     *
     * @param string[] $columns
     * @param \Magento\Catalog\Model\Product $product
     * @param array<string, string> $row
     * @return array<int, array<string, string>>
     */
    public function expandRows(
        array $columns,
        ProductsProfile|PromoProfile $profile,
        $product,
        array $row
    ): array {
        return [$row];
    }

    /**
     * Bumps the resolved Price value by the profile's markup percentage, e.g. to cover a
     * marketplace fee shaved off each sale. Left untouched when no markup is configured or the
     * resolved value isn't numeric (a literal default like "N/A" has nothing to mark up).
     */
    private function applyMarkup(string $value, ProductsProfile $profile): string
    {
        $markupPercent = (float) $profile->getData('markup_percent');

        if ($markupPercent <= 0 || !is_numeric($value)) {
            return $value;
        }

        return (string) round((float) $value * (1 + $markupPercent / 100), 4);
    }

    /**
     * PedidosYa's Active column must be strictly "1" or "0" - not Magento's raw status codes
     * (1 = Enabled, 2 = Disabled) or whatever truthy/falsy string a mapped attribute happens to
     * resolve to. Only a value that numerically equals 1 passes through as active; everything
     * else (including "2", "0", "", a literal default like "true", etc.) is exported as "0".
     *
     * Compared numerically rather than as a literal "1" string on purpose: attribute collections
     * that select a decimal attribute (e.g. price) alongside an int one (e.g. status) can return
     * the int attribute formatted as "1.000000" instead of "1" - a real Magento EAV quirk, not a
     * malformed value.
     */
    private function normalizeActive(string $value): string
    {
        return is_numeric($value) && (float) $value === 1.0 ? '1' : '0';
    }

    /**
     * MSI can report negative salable quantity (e.g. oversold stock corrected downward after the
     * fact) - PedidosYa has no use for a negative number, so it's floored at "0". Left untouched
     * when not numeric (a literal default like "N/A" has nothing to floor).
     */
    private function normalizeQuantity(string $value): string
    {
        if (!is_numeric($value)) {
            return $value;
        }

        return (float) $value < 0 ? '0' : $value;
    }

    /**
     * PedidosYa expects GTIN-14-width barcodes; shorter numeric codes (EAN-13, UPC-A, etc.) get
     * left-padded with zeros. Left untouched when empty, non-numeric, or already 14+ characters -
     * a literal default like "N/A", or a code that's already the right width (or wider), has
     * nothing to pad.
     *
     * Public so a row-expansion plugin (see {@see expandRows}) can re-apply the same padding to
     * each individual code it splits a multi-value Barcode column into.
     */
    public function normalizeBarcode(string $value): string
    {
        if ($value === '' || !ctype_digit($value) || strlen($value) >= 14) {
            return $value;
        }

        return str_pad($value, 14, '0', STR_PAD_LEFT);
    }

    /**
     * Bulk-computes Discounted Price straight from the product's base Price, bypassing the
     * "Discounted Price" mapping entirely - a one-knob way to markdown every promo product
     * instead of configuring a per-column source/default. Returns null (leave the mapped value
     * alone) when no markdown is configured or the product has no numeric base price.
     */
    private function applyMarkdown(PromoProfile $profile, $product): ?string
    {
        $markdownPercent = (float) $profile->getData('markdown_percent');
        if ($markdownPercent <= 0) {
            return null;
        }

        $basePrice = $product->getData('price');
        if (!is_numeric($basePrice)) {
            return null;
        }

        return (string) round((float) $basePrice * (1 - $markdownPercent / 100), 4);
    }

    /**
     * @param array<string, array<string, float>> $sourceQtyByCodeAndSku
     */
    private function resolveValue(
        string $source,
        string $default,
        $product,
        int $storeId,
        array $sourceQtyByCodeAndSku
    ): string {
        $value = null;

        if (str_starts_with($source, 'attribute:')) {
            $value = $product->getData(substr($source, strlen('attribute:')));
            if (is_array($value)) {
                $value = implode(',', $value);
            }
        } elseif (str_starts_with($source, 'stock:')) {
            $stockId = (int) substr($source, strlen('stock:'));
            try {
                $value = $this->getProductSalableQty->execute((string) $product->getSku(), $stockId);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'Could not resolve salable qty for SKU %s on stock %d: %s',
                    $product->getSku(),
                    $stockId,
                    $e->getMessage()
                ));
                $value = null;
            }
        } elseif (str_starts_with($source, 'source:')) {
            $sourceCode = substr($source, strlen('source:'));
            $value = $sourceQtyByCodeAndSku[$sourceCode][(string) $product->getSku()] ?? null;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    private function collectAttributeCodes(ProductsProfile|PromoProfile $profile, array $columns): array
    {
        $codes = [];
        foreach ($columns as $column) {
            $source = (string) $profile->getData($column . '_source');
            if (str_starts_with($source, 'attribute:')) {
                $codes[] = substr($source, strlen('attribute:'));
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    private function collectSourceCodes(ProductsProfile|PromoProfile $profile, array $columns): array
    {
        $codes = [];
        foreach ($columns as $column) {
            $source = (string) $profile->getData($column . '_source');
            if (str_starts_with($source, 'source:')) {
                $codes[] = substr($source, strlen('source:'));
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Batches the "quantity from an MSI source" lookup for a whole page of SKUs into one query
     * per source code, instead of one query per product - the per-product version turned into a
     * real N+1 on large catalogs (thousands of round trips for a 40k-SKU export).
     *
     * @param string[] $sourceCodes
     * @param string[] $skus
     * @return array<string, array<string, float>> source_code => [sku => quantity]
     */
    private function prefetchSourceQuantities(array $sourceCodes, array $skus): array
    {
        if (!$sourceCodes || !$skus) {
            return [];
        }

        $map = [];

        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCodes, 'in')
                ->addFilter(SourceItemInterface::SKU, $skus, 'in')
                ->create();

            foreach ($this->sourceItemRepository->getList($searchCriteria)->getItems() as $sourceItem) {
                $map[(string) $sourceItem->getSourceCode()][(string) $sourceItem->getSku()] = $sourceItem->getQuantity();
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'Could not prefetch source quantities for sources [%s]: %s',
                implode(',', $sourceCodes),
                $e->getMessage()
            ));
        }

        return $map;
    }

    /**
     * @return string[]
     */
    private function parseSkuList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $skus = array_map('trim', preg_split('/[\r\n,]+/', $raw) ?: []);

        return array_values(array_unique(array_filter($skus, static fn (string $sku): bool => $sku !== '')));
    }
}

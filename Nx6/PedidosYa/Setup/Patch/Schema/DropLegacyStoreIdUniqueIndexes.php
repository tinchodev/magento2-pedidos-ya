<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * On environments where these tables predate this module's declarative schema naming
 * convention, the unique index on store_id was created under a legacy name (e.g.
 * "NX6_PEDIDOSYA_PRODUCTS_PROFILE_STORE_ID" instead of the declared "..._UNIQUE" referenceId).
 * Declarative schema only manages elements it can match by name, so that stray index is
 * never dropped by setup:upgrade on its own - it has to be found and removed imperatively here.
 */
class DropLegacyStoreIdUniqueIndexes implements SchemaPatchInterface
{
    private const array TABLE_FK_INDEXES = [
        'nx6_pedidosya_products_profile' => 'NX6_PEDIDOSYA_PRODUCTS_PROFILE_STORE_ID_STORE_STORE_ID',
        'nx6_pedidosya_promo_profile' => 'NX6_PEDIDOSYA_PROMO_PROFILE_STORE_ID_STORE_STORE_ID',
    ];

    public function __construct(
        private readonly SchemaSetupInterface $schemaSetup
    ) {
    }

    #[\Override]
    public function apply(): void
    {
        $this->schemaSetup->startSetup();
        $adapter = $this->schemaSetup->getConnection();

        foreach (self::TABLE_FK_INDEXES as $table => $fkIndexName) {
            $tableName = $this->schemaSetup->getTable($table);
            if (!$adapter->isTableExists($tableName)) {
                continue;
            }

            $indexList = $adapter->getIndexList($tableName);
            $existingIndexNames = array_column($indexList, 'KEY_NAME');

            foreach ($indexList as $index) {
                if ($index['INDEX_TYPE'] !== 'unique') {
                    continue;
                }
                if ($index['COLUMNS_LIST'] !== ['store_id']) {
                    continue;
                }
                // MySQL refuses to drop the index backing a foreign key unless another
                // index on the same column already exists, so add the replacement first.
                // indexExists() isn't part of AdapterInterface (only Magento's core Pdo\Mysql
                // implements it), and on this install the setup connection resolves to
                // Firebear_ImportExport's adapter interceptor, which doesn't proxy it - so
                // check against the index list already fetched above instead.
                if (!in_array($fkIndexName, $existingIndexNames, true)) {
                    $adapter->addIndex($tableName, $fkIndexName, ['store_id']);
                }

                $adapter->dropIndex($tableName, $index['KEY_NAME']);
            }
        }

        $this->schemaSetup->endSetup();
    }

    #[\Override]
    public static function getDependencies(): array
    {
        return [];
    }

    #[\Override]
    public function getAliases(): array
    {
        return [];
    }
}

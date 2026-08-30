<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Ui\Component\Form;

use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;
use Nx6\PedidosYa\Model\Config\Source\Attributes;
use Nx6\PedidosYa\Model\Config\Source\AttributesAndStocks;
use Nx6\PedidosYa\Model\Config\Source\PromotionSubType;
use Nx6\PedidosYa\Model\Config\Source\PromotionType;
use Nx6\PedidosYa\Model\ExportColumns;

/**
 * Builds the "CSV Field Mapping" fieldset as a grid, one row per export column, with each
 * row's fields scoped directly to the "<column>_source" / "<column>_default" columns on the
 * profile entity - real columns instead of a JSON blob.
 *
 * $registryKey and $columnsMethod are injected per profile type via di.xml virtualTypes, so
 * this one class serves both the products and promo profile forms.
 */
class MappingModifier implements ModifierInterface
{
    /**
     * Name of the fieldset declared in both ui_component form XMLs.
     */
    private const string FIELDSET = 'mapping';

    /**
     * Columns whose "default" cell should be a datepicker instead of a plain text input.
     */
    private const array DATE_COLUMNS = ['start_date', 'end_date'];

    public function __construct(
        private readonly Registry $coreRegistry,
        private readonly ExportColumns $exportColumns,
        private readonly Attributes $attributes,
        private readonly AttributesAndStocks $attributesAndStocks,
        private readonly PromotionType $promotionType,
        private readonly PromotionSubType $promotionSubType,
        private readonly string $registryKey,
        private readonly string $columnsMethod
    ) {
    }

    #[\Override]
    public function modifyData(array $data): array
    {
        return $data;
    }

    #[\Override]
    public function modifyMeta(array $meta): array
    {
        $model = $this->coreRegistry->registry($this->registryKey);

        if (!$model) {
            return $meta;
        }

        $rows = [];
        $sortOrder = 10;
        foreach ($this->exportColumns->{$this->columnsMethod}() as $column => $label) {
            $rows['mapping_' . $column] = $this->buildRow($column, $label, $sortOrder);
            $sortOrder += 10;
        }

        // The fieldset itself (label, notice, classes) is declared in the form XML; the
        // factory merges this metadata into it via UiComponentFactory::mergeMetadata().
        $meta[self::FIELDSET]['children'] = [
            'table' => [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => 'container',
                            'component' => 'Magento_Ui/js/lib/core/collection',
                            // Magento's template loader inserts "/template" after the module
                            // name (see Magento_Ui/js/lib/knockout/template/loader::formatPath),
                            // so this resolves to Nx6_PedidosYa/template/mapping-table.html.
                            'template' => 'Nx6_PedidosYa/mapping-table',
                        ],
                    ],
                ],
                'children' => $rows,
            ],
        ];

        return $meta;
    }

    /**
     * A table row. The template reads its "label" for the Title cell and renders each
     * child's elementTmpl into a cell of its own.
     */
    private function buildRow(string $column, string $label, int $sortOrder): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'container',
                        'component' => 'Magento_Ui/js/lib/core/collection',
                        'label' => __($label),
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
            'children' => [
                'source' => $this->buildSourceField($column),
                'default' => $this->buildDefaultCell($column),
            ],
        ];
    }

    private function buildDefaultCell(string $column): array
    {
        return match ($column) {
            'promotion_type' => $this->buildDefaultSelectField($column, $this->promotionType->toOptionArray()),
            'promotion_sub_type' => $this->buildDefaultSelectField($column, $this->promotionSubType->toOptionArray()),
            default => in_array($column, self::DATE_COLUMNS, true)
                ? $this->buildDefaultDateField($column)
                : $this->buildDefaultField($column),
        };
    }

    private function buildSourceField(string $column): array
    {
        $options = $this->exportColumns->isStockMappable($column)
            ? $this->attributesAndStocks->toOptionArray()
            : $this->attributes->toOptionArray();

        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'field',
                        'formElement' => 'select',
                        'component' => 'Magento_Ui/js/form/element/select',
                        'elementTmpl' => 'ui/form/element/select',
                        'dataType' => 'text',
                        'dataScope' => $column . '_source',
                        'options' => $options,
                        'sortOrder' => 10,
                    ],
                ],
            ],
        ];
    }

    private function buildDefaultField(string $column): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'field',
                        'formElement' => 'input',
                        'component' => 'Magento_Ui/js/form/element/abstract',
                        'elementTmpl' => 'ui/form/element/input',
                        'dataType' => 'text',
                        'dataScope' => $column . '_default',
                        'sortOrder' => 20,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{value: string, label: Phrase}> $options
     */
    private function buildDefaultSelectField(string $column, array $options): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'field',
                        'formElement' => 'select',
                        'component' => 'Magento_Ui/js/form/element/select',
                        'elementTmpl' => 'ui/form/element/select',
                        'dataType' => 'text',
                        'dataScope' => $column . '_default',
                        'options' => $options,
                        'sortOrder' => 20,
                    ],
                ],
            ],
        ];
    }

    /**
     * The stored value is a literal CSV string ("Y-m-d H:i:s", produced server-side by
     * Promo\Profile\Save via Magento\Framework\Stdlib\DateTime\Filter\DateTime), not a real
     * date column - only the picker widget changes here, not the underlying storage.
     */
    private function buildDefaultDateField(string $column): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'field',
                        'formElement' => 'date',
                        'component' => 'Magento_Ui/js/form/element/date',
                        'elementTmpl' => 'ui/form/element/date',
                        'dataType' => 'text',
                        'dataScope' => $column . '_default',
                        'options' => [
                            'showsTime' => true,
                            'dateFormat' => 'MM/dd/yyyy',
                            'timeFormat' => 'hh:mm a',
                        ],
                        'sortOrder' => 20,
                    ],
                ],
            ],
        ];
    }
}

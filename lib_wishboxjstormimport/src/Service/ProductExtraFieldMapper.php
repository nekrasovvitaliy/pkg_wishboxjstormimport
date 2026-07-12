<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\CMS\Filter\OutputFilter;

defined('_JEXEC') or die;

/**
 * Builds RadicalMart field payloads and product field values.
 *
 * @since 1.0.0
 */
final readonly class ProductExtraFieldMapper
{
    /**
     * @return array<string, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function directFieldDefinitions(): array
    {
        return [
            'direct:wildberries_barcode' => [
                'source_key' => 'wildberries_barcode',
                'title' => 'WildBerries barcode',
                'alias' => 'wildberries-barcode',
                'type' => 'text',
            ],
            'direct:wildberries_nmid' => [
                'source_key' => 'wildberries_nmid',
                'title' => 'Wildberries Id',
                'alias' => 'wildberries-id',
                'type' => 'text',
            ],
            'direct:wishboxwildberries_product_price_ratio' => [
                'source_key' => 'wishboxwildberries_product_price_ratio',
                'title' => 'Wildberries price ratio',
                'alias' => 'wildberries-price-ratio',
                'type' => 'number',
            ],
            'direct:wishboxozon_id' => [
                'source_key' => 'wishboxozon_id',
                'title' => 'Ozon id',
                'alias' => 'ozon-id',
                'type' => 'text',
            ],
            'direct:wishboxozon_product_price_ratio' => [
                'source_key' => 'wishboxozon_product_price_ratio',
                'title' => 'Ozon price ratio',
                'alias' => 'ozon-price-ratio',
                'type' => 'number',
            ],
        ];
    }

    /**
     * @param   array  $source          Source row.
     * @param   array  $fieldValues     Extra field values.
     * @param   array  $relationValues  Product relation values.
     * @param   array  $categoryMap     Category map.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function fieldData(array $source, array $fieldValues, array $relationValues, array $categoryMap): array
    {
        $oldFieldId = trim((string) ($source['id'] ?? ''));
        $type = $this->radicalMartExtraFieldType($source, $relationValues);
        $options = in_array($type, ['list', 'checkboxes'], true) ? $this->fieldOptions($fieldValues) : [];
        $categories = $this->mappedCategoryIds($this->oldCategoryIds($source), $categoryMap);
        $allCategories = (int) ($source['allcats'] ?? 1) === 1 || $categories === [];

        return [
            'id' => 0,
            'title' => $this->firstFilled($source, 'name_ru-RU'),
            'alias' => $this->fieldAlias($source),
            'area' => 'products',
            'all_categories' => $allCategories ? 1 : 0,
            'categories' => $categories,
            'plugin' => 'standard',
            'fieldset_administrator' => 0,
            'fieldset_site' => 0,
            'description' => $this->firstFilled($source, 'description_ru-RU'),
            'options' => $options,
            'note' => '',
            'state' => 1,
            'params' => [
                'type' => $type,
                'required' => 0,
                'multiple' => $type === 'checkboxes' ? 1 : 0,
                'null_value' => 1,
                'display_products' => 1,
                'display_products_as' => 'string',
                'display_product' => 1,
                'display_product_as' => 'string',
                'display_filter' => in_array($type, ['list', 'checkboxes', 'number'], true) ? 1 : 0,
                'display_filter_as' => $type === 'checkboxes' ? 'checkboxes' : 'list',
                'display_filter_operator' => 'or',
                'display_variability' => 0,
                'import_old_extra_field_id' => $oldFieldId,
            ],
            'plugins' => [],
            'language' => '*',
        ];
    }

    /**
     * @param   array  $fieldValues  Extra field values.
     * @param   array  $options      RadicalMart options.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function fieldValueMap(array $fieldValues, array $options): array
    {
        $map = [];

        foreach ($fieldValues as $oldValueId => $value) {
            $text = $this->firstFilled($value, 'name_ru-RU');

            foreach ($options as $option) {
                if (($option['text'] ?? '') === $text) {
                    $map[(string) $oldValueId] = (string) $option['value'];

                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param   string  $oldProductId          Old product ID.
     * @param   array   $extraFieldMap         Extra field map.
     * @param   array   $productExtraFieldMap  Product extra field map.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function productFields(string $oldProductId, array $extraFieldMap, array $productExtraFieldMap): array
    {
        $fields = [];

        foreach ($productExtraFieldMap[$oldProductId] ?? [] as $oldFieldId => $rawValue) {
            if (!isset($extraFieldMap[$oldFieldId])) {
                continue;
            }

            $field = $extraFieldMap[$oldFieldId];

            if (($field['type'] ?? '') === 'shipping') {
                continue;
            }

            $value = $this->normaliseProductFieldValue((string) $rawValue, $field);

            if ($value === '' || $value === []) {
                continue;
            }

            $fields[$field['alias']] = $value;
        }

        return $fields;
    }

    /**
     * @param   array  $source          Source row.
     * @param   array  $extraFieldMap   Extra field map.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function productDirectFields(array $source, array $extraFieldMap): array
    {
        $fields = [];

        foreach ($this->directFieldDefinitions() as $mapKey => $definition) {
            if (!isset($extraFieldMap[$mapKey])) {
                continue;
            }

            $sourceKey = (string) ($definition['source_key'] ?? '');
            $value = trim((string) ($source[$sourceKey] ?? ''));

            if ($value === '') {
                continue;
            }

            $fields[$extraFieldMap[$mapKey]['alias']] = $this->normaliseDecimalValue($value);
        }

        return $fields;
    }


    /**
     * @param   array  $source  Source row.
     *
     * @return array|null
     *
     * @since 1.0.0
     */
    public function shippingFieldMap(array $source): ?array
    {
        $shippingKey = $this->shippingKey($source);

        if ($shippingKey === null) {
            return null;
        }

        return [
            'id' => 0,
            'alias' => 'shipping_' . $shippingKey,
            'type' => 'shipping',
            'shipping_key' => $shippingKey,
            'options' => [],
            'value_map' => [],
        ];
    }

    /**
     * @param   string  $oldProductId          Old product ID.
     * @param   array   $extraFieldMap         Extra field map.
     * @param   array   $productExtraFieldMap  Product extra field map.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function productShipping(string $oldProductId, array $extraFieldMap, array $productExtraFieldMap): array
    {
        $shipping = [
            'enable' => 1,
            'dimensions_units' => 'cm',
        ];

        foreach ($productExtraFieldMap[$oldProductId] ?? [] as $oldFieldId => $rawValue) {
            if (!isset($extraFieldMap[$oldFieldId])) {
                continue;
            }

            $field = $extraFieldMap[$oldFieldId];
            $shippingKey = (string) ($field['shipping_key'] ?? '');

            if (($field['type'] ?? '') !== 'shipping' || $shippingKey === '') {
                continue;
            }

            $value = $this->normaliseDecimalValue((string) $rawValue);

            if ($value === '') {
                continue;
            }

            $shipping[$shippingKey] = $value;
        }

        return $shipping;
    }

    /**
     * @param   array  $source  Source row.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    private function shippingKey(array $source): ?string
    {
        $title = $this->firstFilled($source, 'name_ru-RU');
        $title = function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title);

        return match ($title) {
            'длина' => 'length',
            'ширина' => 'width',
            'высота' => 'height',
            default => null,
        };
    }

    /**
     * @param   string  $oldFieldId  Old field ID.
     * @param   array   $relations   Relations map.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function relationValues(string $oldFieldId, array $relations): array
    {
        $values = [];

        foreach ($relations as $fields) {
            if (isset($fields[$oldFieldId]) && trim((string) $fields[$oldFieldId]) !== '') {
                $values[] = trim((string) $fields[$oldFieldId]);
            }
        }

        return $values;
    }

    /**
     * @param   array  $fieldValues  Extra field values.
     *
     * @return array
     *
     * @since 1.0.0
     */
    private function fieldOptions(array $fieldValues): array
    {
        $options = [];
        $values = [];
        $ordering = 0;

        foreach ($fieldValues as $value) {
            $text = $this->firstFilled($value, 'name_ru-RU');

            if ($text === '') {
                continue;
            }

            $optionValue = OutputFilter::stringURLSafe($text);

            if ($optionValue === '') {
                $optionValue = 'value-' . ($value['id'] ?? $ordering);
            }

            while (in_array($optionValue, $values, true)) {
                $optionValue .= '-' . count($values);
            }

            $values[] = $optionValue;
            $options[$optionValue] = [
                'text' => $text,
                'value' => $optionValue,
                'image' => '',
                'option_ordering' => $ordering,
                'option_categories' => '',
            ];
            $ordering++;
        }

        return $options;
    }

    /**
     * @param   string  $rawValue  Raw value.
     * @param   array   $field     Field data.
     *
     * @return array|string
     *
     * @since 1.0.0
     */
    private function normaliseProductFieldValue(string $rawValue, array $field): array|string
    {
        if (in_array($field['type'], ['list', 'checkboxes'], true)) {
            $values = [];

            foreach (explode(',', $rawValue) as $oldValueId) {
                $oldValueId = trim($oldValueId);

                if ($oldValueId !== '' && isset($field['value_map'][$oldValueId])) {
                    $values[] = $field['value_map'][$oldValueId];
                }
            }

            $values = array_values(array_unique($values));

            if ($field['type'] === 'list') {
                return $values[0] ?? '';
            }

            return $values;
        }

        return str_replace(',', '.', trim($rawValue));
    }

    /**
     * @param   string  $value  Raw value.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function normaliseDecimalValue(string $value): string
    {
        return str_replace(',', '.', trim($value));
    }

    /**
     * @param   array  $source  Source row.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    private function oldCategoryIds(array $source): array
    {
        $cats = trim((string) ($source['cats'] ?? ''));

        if ($cats === '') {
            return [];
        }

        $categoryIds = @unserialize($cats, ['allowed_classes' => false]);

        if (!is_array($categoryIds)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn($categoryId): string => trim((string) $categoryId),
                    $categoryIds
                ),
                static fn(string $categoryId): bool => $categoryId !== ''
            )
        );
    }

    /**
     * @param   array  $source          Source row.
     * @param   array  $relationValues  Relation values.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function radicalMartExtraFieldType(array $source, array $relationValues): string
    {
        if ((int) ($source['type'] ?? 0) === 0) {
            return (int) ($source['multilist'] ?? 0) === 1 ? 'checkboxes' : 'list';
        }

        if (array_any($relationValues, fn($value) => !is_numeric(str_replace(',', '.', $value)))) {
            return 'text';
        }

        return $relationValues === [] ? 'text' : 'number';
    }

    /**
     * @param   array  $source  Source row.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function fieldAlias(array $source): string
    {
        $alias = OutputFilter::stringURLSafe($this->firstFilled($source, 'name_ru-RU'));

        if ($alias === '') {
            $alias = 'extra-field-' . trim((string) ($source['id'] ?? ''));
        }

        return 'jshopping-' . $alias;
    }

    /**
     * @param   array  $oldCategoryIds  Old category IDs.
     * @param   array  $categoryMap     Category map.
     *
     * @return integer[]
     *
     * @since 1.0.0
     */
    private function mappedCategoryIds(array $oldCategoryIds, array $categoryMap): array
    {
        $categoryIds = [];

        foreach ($oldCategoryIds as $oldCategoryId) {
            if (isset($categoryMap[$oldCategoryId])) {
                $categoryIds[] = $categoryMap[$oldCategoryId];
            }
        }

        return array_values(array_unique($categoryIds));
    }

    /**
     * @param   array  $source  Source row.
     * @param   string ...$keys Keys.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function firstFilled(array $source, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($source[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

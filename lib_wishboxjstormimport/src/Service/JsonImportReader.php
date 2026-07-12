<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use RuntimeException;

defined('_JEXEC') or die;

/**
 * Reads source JSON files exported from jShopping.
 *
 * @since 1.0.0
 */
final readonly class JsonImportReader
{
    /**
     * @param   string  $path   JSON path.
     * @param   string  $label  Error label.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function readRows(string $path, string $label): array
    {
        if (!is_file($path)) {
            throw new RuntimeException($label . ' JSON was not found: ' . $path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read ' . strtolower($label) . ' JSON: ' . $path);
        }

        $rows = json_decode($contents, true);

        if (!is_array($rows)) {
            throw new RuntimeException('Invalid ' . strtolower($label) . ' JSON: ' . json_last_error_msg());
        }

        if (isset($rows['data']) && is_array($rows['data'])) {
            return $rows['data'];
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['type'] ?? '') === 'table' && isset($row['data']) && is_array($row['data'])) {
                return $row['data'];
            }
        }

        return $rows;
    }

    /**
     * @param   string  $path  JSON path.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function readProductCategoryRelations(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $map = [];

        foreach ($this->readRows($path, 'Product-category relations') as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $productId = trim((string) ($relation['product_id'] ?? ''));
            $categoryId = trim((string) ($relation['category_id'] ?? ''));

            if ($productId === '' || $categoryId === '') {
                continue;
            }

            $map[$productId][] = [
                'category_id' => $categoryId,
                'ordering' => (int) ($relation['product_ordering'] ?? 0),
            ];
        }

        foreach ($map as &$productRelations) {
            usort(
                $productRelations,
                static fn(array $a, array $b): int => $a['ordering'] <=> $b['ordering']
            );
        }
        unset($productRelations);

        return $map;
    }

    /**
     * @param   string  $path  JSON path.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function readProductImageRelations(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $map = [];

        foreach ($this->readRows($path, 'Product-image relations') as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $productId = trim((string) ($relation['product_id'] ?? ''));
            $imageName = trim((string) ($relation['image_name'] ?? ''));

            if ($productId === '' || $imageName === '') {
                continue;
            }

            $map[$productId][] = [
                'image_name' => $imageName,
                'ordering' => (int) ($relation['ordering'] ?? 0),
                'image_id' => (int) ($relation['image_id'] ?? 0),
                'title' => trim((string) ($relation['title'] ?? '')),
                'name' => trim((string) ($relation['name'] ?? '')),
            ];
        }

        foreach ($map as &$productImages) {
            usort(
                $productImages,
                static fn(array $a, array $b): int => [$a['ordering'], $a['image_id']] <=> [$b['ordering'], $b['image_id']]
            );
        }
        unset($productImages);

        return $map;
    }

    /**
     * @param   string  $path  JSON path.
     *
     * @return array<string, array>
     *
     * @since 1.0.0
     */
    public function readProductExtraFieldValues(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $map = [];

        foreach ($this->readRows($path, 'Product extra field values') as $row) {
            if (!is_array($row)) {
                continue;
            }

            $fieldId = trim((string) ($row['field_id'] ?? ''));
            $valueId = trim((string) ($row['id'] ?? ''));

            if ($fieldId === '' || $valueId === '') {
                continue;
            }

            $map[$fieldId][$valueId] = $row;
        }

        foreach ($map as &$fieldValues) {
            uasort(
                $fieldValues,
                static fn(array $a, array $b): int => (int) ($a['ordering'] ?? 0) <=> (int) ($b['ordering'] ?? 0)
            );
        }
        unset($fieldValues);

        return $map;
    }

    /**
     * @param   string  $path  JSON path.
     *
     * @return array<string, array>
     *
     * @since 1.0.0
     */
    public function readProductExtraFieldRelations(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $map = [];

        foreach ($this->readRows($path, 'Product extra field relations') as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = trim((string) ($row['product_id'] ?? ''));

            if ($productId === '') {
                continue;
            }

            foreach ($row as $key => $value) {
                if (!str_starts_with((string) $key, 'extra_field_')) {
                    continue;
                }

                $fieldId = substr((string) $key, strlen('extra_field_'));
                $fieldValue = trim((string) $value);

                if ($fieldId === '' || $fieldValue === '') {
                    continue;
                }

                $map[$productId][$fieldId] = $fieldValue;
            }
        }

        return $map;
    }
}

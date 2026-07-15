<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\CMS\Filter\OutputFilter;

defined('_JEXEC') or die;

/**
 * Builds RadicalMart category, manufacturer, and product payloads.
 *
 * @since 1.0.0
 */
final readonly class RadicalMartDataMapper
{
    /**
     * @param   ImageImportService       $imageImportService  Image import service.
     * @param   ProductExtraFieldMapper  $fieldMapper         Field mapper.
     *
     * @since 1.0.0
     */
    public function __construct(
        private ImageImportService $imageImportService,
        private ProductExtraFieldMapper $fieldMapper
    ) {
    }

    /**
     * @param   array    $source      Source row.
     * @param   integer  $parentId    Parent ID.
     * @param   integer  $existingId  Existing ID.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function categoryData(array $source, int $parentId, int $existingId = 0): array
    {
        $title = $this->categoryTitle($source);
        $alias = $this->categoryAlias($source);
        $description = $this->firstFilled($source, 'description_ru-RU', 'description');
        $image = $this->firstFilled($source, 'category_image', 'image');
        $metaTitle = $this->firstFilled($source, 'meta_title_ru-RU');
        $metaDescription = $this->firstFilled($source, 'meta_description_ru-RU');
        $metaKeyword = $this->firstFilled($source, 'meta_keyword_ru-RU');
        $imagePath = $this->imageImportService->categoryImageValue($image);
        $data = [
            'id' => $existingId,
            'parent_id' => $parentId,
            'title' => $title,
            'alias' => $alias,
            'type' => 'category',
            'introtext' => '',
            'fulltext' => $description,
            'search_text' => trim(strip_tags($description)),
            'state' => (int) ($source['category_publish'] ?? 1),
            'show' => 1,
            'language' => '*',
            'media' => [],
            'params' => [
                'import_old_category_id' => trim((string) ($source['category_id'] ?? '')),
                'import_old_category_parent_id' => trim((string) ($source['category_parent_id'] ?? '')),
                'category_template' => trim((string) ($source['category_template'] ?? '')),
                'category_add_date' => trim((string) ($source['category_add_date'] ?? '')),
                'source_name_ru-RU' => trim((string) ($source['name_ru-RU'] ?? '')),
                'source_alias_ru-RU' => trim((string) ($source['alias_ru-RU'] ?? '')),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'meta_keyword' => $metaKeyword,
            ],
            'metadata' => [
                'metatitle' => $metaTitle,
                'metadesc' => $metaDescription,
                'metakey' => $metaKeyword,
            ],
            'plugins' => [],
            'totals' => [
                'products' => -1,
                'metas' => -1,
                'items' => -1,
            ],
        ];

        if ($imagePath !== '') {
            $data['media']['image'] = $imagePath;
        }

        return $data;
    }

    /**
     * @param   array  $source  Source row.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function categoryTitle(array $source): string
    {
        return $this->firstFilled($source, 'name_ru-RU', 'title', 'name');
    }

    /**
     * @param   array  $source  Source row.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function categoryAlias(array $source): string
    {
        $alias = $this->firstFilled($source, 'alias_ru-RU', 'alias');

        if ($alias !== '') {
            return $alias;
        }

        $title = $this->categoryTitle($source);

        return $title !== '' ? OutputFilter::stringURLSafe($title) : '';
    }

    /**
     * @param   array    $source      Source row.
     * @param   integer  $parentId    Parent ID.
     * @param   integer  $existingId  Existing ID.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function manufacturerData(array $source, int $parentId, int $existingId = 0): array
    {
        $description = $this->firstFilled($source, 'description_ru-RU', 'description');
        $metaTitle = $this->firstFilled($source, 'meta_title_ru-RU');
        $metaDescription = $this->firstFilled($source, 'meta_description_ru-RU');
        $metaKeyword = $this->firstFilled($source, 'meta_keyword_ru-RU');

        return [
            'id' => $existingId,
            'parent_id' => $parentId,
            'title' => $this->firstFilled($source, 'name_ru-RU', 'title'),
            'alias' => $this->firstFilled($source, 'alias_ru-RU', 'alias'),
            'type' => 'manufacturer',
            'introtext' => '',
            'fulltext' => $description,
            'search_text' => trim(strip_tags($description)),
            'state' => (int) ($source['manufacturer_publish'] ?? 1),
            'show' => 1,
            'language' => '*',
            'media' => [],
            'params' => [
                'import_old_manufacturer_id' => trim((string) ($source['manufacturer_id'] ?? '')),
                'manufacturer_url' => trim((string) ($source['manufacturer_url'] ?? '')),
                'manufacturer_logo' => trim((string) ($source['manufacturer_logo'] ?? '')),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'meta_keyword' => $metaKeyword,
            ],
            'metadata' => [
                'metatitle' => $metaTitle,
                'metadesc' => $metaDescription,
                'metakey' => $metaKeyword,
            ],
            'plugins' => [],
            'totals' => [
                'products' => -1,
                'metas' => -1,
                'items' => -1,
            ],
        ];
    }

    /**
     * @param   array    $source                Source row.
     * @param   array    $categoryMap           Category map.
     * @param   array    $productCategoryMap    Product category map.
     * @param   array    $productImageMap       Product image map.
     * @param   array    $manufacturerMap       Manufacturer map.
     * @param   array    $extraFieldMap         Extra field map.
     * @param   array    $productExtraFieldMap  Product extra field map.
     * @param   string   $imageSourceDir        Image source dir.
     * @param   string   $imageTargetDir        Image target dir.
     * @param   integer  $existingId            Existing ID.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function productData(
        array $source,
        array $categoryMap,
        array $productCategoryMap,
        array $productImageMap,
        array $manufacturerMap,
        array $extraFieldMap,
        array $productExtraFieldMap,
        string $imageSourceDir,
        string $imageTargetDir,
        int $existingId = 0
    ): array {
        $title = $this->firstFilled($source, 'name_ru-RU');
        $introtext = $this->firstFilled($source, 'short_description_ru-RU');
        $fulltext = $this->firstFilled($source, 'description_ru-RU');
        $oldProductId = trim((string) ($source['product_id'] ?? ''));
        $oldCategoryId = trim((string) ($source['main_category_id'] ?? ''));
        $oldManufacturerId = trim((string) ($source['product_manufacturer_id'] ?? ''));
        $oldCategoryIds = $this->productOldCategoryIds($oldProductId, $oldCategoryId, $productCategoryMap);
        $categoryIds = $this->mappedCategoryIds($oldCategoryIds, $categoryMap);
        $categoryId = ($oldCategoryId !== '' && isset($categoryMap[$oldCategoryId])) ? $categoryMap[$oldCategoryId] : ($categoryIds[0] ?? 0);
        $additionalCategoryIds = array_values(array_diff($categoryIds, [$categoryId]));
        $manufacturerId = $manufacturerMap[$oldManufacturerId] ?? 0;
        $price = $this->decimalValue($source, 'product_price');
        $oldPrice = $this->decimalValue($source, 'product_old_price');
        $buyPrice = $this->decimalValue($source, 'product_buy_price');
        $weight = $this->decimalValue($source, 'product_weight');
        $quantity = $this->quantityValue($source);
        $imagePaths = $this->imageImportService->productImageValues($source, $productImageMap, $imageSourceDir, $imageTargetDir);
        $fields = $this->fieldMapper->productFields($oldProductId, $extraFieldMap, $productExtraFieldMap);
        $fields = array_merge($fields, $this->fieldMapper->productDirectFields($source, $extraFieldMap));
        $shipping = $this->fieldMapper->productShipping($oldProductId, $extraFieldMap, $productExtraFieldMap);
        $shipping['weight_unit'] = 'kg';
        $shipping['weight'] = $weight;
        $data = [
            'id' => $existingId,
            'title' => $title,
            'alias' => $this->firstFilled($source, 'alias_ru-RU'),
            'introtext' => $introtext,
            'fulltext' => $fulltext,
            'search_text' => trim(strip_tags($title . ' ' . $introtext . ' ' . $fulltext)),
            'state' => (int) ($source['product_publish'] ?? 0),
            'show' => 1,
            'language' => '*',
            'created_by' => 962,
            'sku' => trim((string) ($source['product_ean'] ?? '')),
            'vendor_code' => trim((string) ($source['manufacturer_code'] ?? '')),
            'quantity' => $quantity,
            'stock' => [
                'all' => $quantity,
            ],
            'unlimited' => (int) ($source['unlimited'] ?? 0),
            'weight' => (float) $weight,
            'price' => $price,
            'old_price' => $oldPrice,
            'buy_price' => $buyPrice,
            'category' => $categoryId,
            'category_id' => $categoryId,
            'categories_additional_categories' => $additionalCategoryIds,
            'categories_additional_manufacturers' => $manufacturerId > 0 ? [$manufacturerId] : [],
            'categories' => $categoryIds,
            'manufacturer_id' => $manufacturerId,
            'manufacturer' => $manufacturerId,
            'fields' => $fields,
            'shipping' => $shipping,
            'media' => [],
            'prices' => $this->productPrices($price, $oldPrice, $buyPrice),
            'params' => [
                'import_old_product_id' => $oldProductId,
                'import_old_category_id' => $oldCategoryId,
                'import_old_category_ids' => $oldCategoryIds,
                'import_old_manufacturer_id' => $oldManufacturerId,
                'stock_accounting' => 1,
                'product_tax_id' => trim((string) ($source['product_tax_id'] ?? '')),
                'product_template' => trim((string) ($source['product_template'] ?? '')),
                'date_added' => trim((string) ($source['product_date_added'] ?? '')),
                'date_modify' => trim((string) ($source['date_modify'] ?? '')),
                'meta_title' => trim((string) ($source['meta_title_ru-RU'] ?? '')),
                'meta_description' => trim((string) ($source['meta_description_ru-RU'] ?? '')),
                'meta_keyword' => trim((string) ($source['meta_keyword_ru-RU'] ?? '')),
            ],
            'metadata' => [
                'metatitle' => trim((string) ($source['meta_title_ru-RU'] ?? '')),
                'metadesc' => trim((string) ($source['meta_description_ru-RU'] ?? '')),
                'metakey' => trim((string) ($source['meta_keyword_ru-RU'] ?? '')),
            ],
            'plugins' => [],
        ];

        if ($imagePaths !== []) {
            $data['media']['gallery'] = array_map(
                static fn(string $imagePath): array => [
                    'type' => 'image',
                    'src' => $imagePath,
                    'alt' => '',
                    'main' => 0,
                ],
                $imagePaths
            );
        }

        return $data;
    }

    /**
     * @param   string  $price     Price.
     * @param   string  $oldPrice  Old price.
     * @param   string  $buyPrice  Buy price.
     *
     * @return array
     *
     * @since 1.0.0
     */
    private function productPrices(string $price, string $oldPrice, string $buyPrice): array
    {
        $basePrice = (float) $price;
        $comparePrice = (float) $oldPrice;
        $discount = 0.0;

        if ($comparePrice > $basePrice && $basePrice > 0.0) {
            $discount = $comparePrice - $basePrice;
            $basePrice = $comparePrice;
        }

        return [
            'rub' => [
                'currency' => 'RUB',
                'base' => $this->formatDecimal($basePrice),
                'purchase' => $buyPrice,
                'purchase_enable' => 0,
                'extra' => '',
                'discount_enable' => $discount > 0.0 ? 1 : 0,
                'discount' => $discount > 0.0 ? $this->formatDecimal($discount) : '',
                'discount_end' => '',
                'hide' => 0,
            ],
        ];
    }

    /**
     * @param   string  $oldProductId       Old product ID.
     * @param   string  $oldMainCategoryId  Old main category ID.
     * @param   array   $productCategoryMap Product category map.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function productOldCategoryIds(string $oldProductId, string $oldMainCategoryId, array $productCategoryMap): array
    {
        $oldCategoryIds = [];

        if (isset($productCategoryMap[$oldProductId])) {
            foreach ($productCategoryMap[$oldProductId] as $relation) {
                $categoryId = trim((string) ($relation['category_id'] ?? ''));

                if ($categoryId !== '') {
                    $oldCategoryIds[] = $categoryId;
                }
            }
        }

        if ($oldMainCategoryId !== '' && !in_array($oldMainCategoryId, $oldCategoryIds, true)) {
            array_unshift($oldCategoryIds, $oldMainCategoryId);
        }

        return array_values(array_unique($oldCategoryIds));
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
     * @param   array   $source  Source row.
     * @param   string  $key     Key.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function decimalValue(array $source, string $key): string
    {
        return number_format((float) ($source[$key] ?? 0), 6, '.', '');
    }

    /**
     * @param   array  $source  Source row.
     *
     * @return float
     *
     * @since 1.0.0
     */
    private function quantityValue(array $source): float
    {
        $quantity = (float) ($source['product_quantity'] ?? 0);

        return $quantity > 0.0 ? $quantity : 0.0;
    }

    /**
     * @param   float  $value  Value.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function formatDecimal(float $value): string
    {
        return number_format($value, 6, '.', '');
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

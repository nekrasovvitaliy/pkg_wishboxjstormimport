<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Dto;

defined('_JEXEC') or die;

/**
 * Catalog import options.
 *
 * @since 1.0.0
 */
final class ImportOptions
{
    /**
     * @since 1.0.0
     */
    public bool $dryRun = false;

    /**
     * @since 1.0.0
     */
    public bool $updateExisting = false;

    /**
     * @since 1.0.0
     */
    public int $parentId = 1;

    /**
     * @since 1.0.0
     */
    public string $categoriesJson = 'jshopping_categories.json';

    /**
     * @since 1.0.0
     */
    public string $manufacturersJson = 'jshopping_manufacturers.json';

    /**
     * @since 1.0.0
     */
    public string $productsJson = 'jshopping_products.json';

    /**
     * @since 1.0.0
     */
    public string $productCategoriesJson = 'jshopping_products_to_categories.json';

    /**
     * @since 1.0.0
     */
    public string $productImagesJson = 'jshopping_products_images.json';

    /**
     * @since 1.0.0
     */
    public string $productExtraFieldsJson = 'jshopping_products_extra_fields.json';

    /**
     * @since 1.0.0
     */
    public string $productExtraFieldValuesJson = 'jshopping_products_extra_field_values.json';

    /**
     * @since 1.0.0
     */
    public string $productExtraFieldRelationsJson = 'jshopping_products_to_extra_fields.json';

    /**
     * @since 1.0.0
     */
    public string $imageSourceDir = 'img_products';

    /**
     * @since 1.0.0
     */
    public string $imageTargetDir = 'img_products';
}

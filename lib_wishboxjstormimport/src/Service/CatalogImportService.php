<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use WishboxJsToRmImportLibrary\Dto\ImportOptions;
use WishboxJsToRmImportLibrary\Dto\ImportResult;

defined('_JEXEC') or die;

/**
 * Imports jShopping catalog data into RadicalMart.
 *
 * @since 1.0.0
 */
final readonly class CatalogImportService
{
    private DatabaseInterface $db;

    private JsonImportReader $reader;

    private RadicalMartMvcFactory $mvcFactoryProvider;

    private RadicalMartLookupService $lookup;

    private ImageImportService $imageImportService;

    private ProductExtraFieldMapper $fieldMapper;

    private RadicalMartDataMapper $dataMapper;

    private RadicalMartSaveService $saveService;

    /**
     * @param   DatabaseInterface               $db                      Database.
     * @param   FormFactoryInterface            $formFactory             Form factory.
     * @param   DispatcherInterface             $dispatcher              Dispatcher.
     * @param   CacheControllerFactoryInterface $cacheControllerFactory  Cache controller factory.
     * @param   UserFactoryInterface            $userFactory             User factory.
     * @param   MailerFactoryInterface          $mailerFactory           Mailer factory.
     *
     * @since 1.0.0
     */
    public function __construct(
        DatabaseInterface $db,
        FormFactoryInterface $formFactory,
        DispatcherInterface $dispatcher,
        CacheControllerFactoryInterface $cacheControllerFactory,
        UserFactoryInterface $userFactory,
        MailerFactoryInterface $mailerFactory
    ) {
        $this->db = $db;
        $this->reader = new JsonImportReader();
        $this->mvcFactoryProvider = new RadicalMartMvcFactory(
            $db,
            $formFactory,
            $dispatcher,
            $cacheControllerFactory,
            $userFactory,
            $mailerFactory
        );
        $this->lookup = new RadicalMartLookupService($db);
        $this->imageImportService = new ImageImportService();
        $this->fieldMapper = new ProductExtraFieldMapper();
        $this->dataMapper = new RadicalMartDataMapper($this->imageImportService, $this->fieldMapper);
        $this->saveService = new RadicalMartSaveService();
    }

    /**
     * @param   ImportOptions  $options  Import options.
     * @param   callable|null  $output   Output callback: fn(string $line): void.
     *
     * @return ImportResult
     *
     * @since 1.0.0
     */
    public function import(ImportOptions $options, ?callable $output = null): ImportResult
    {
        $result = new ImportResult();
        $mvcFactory = $this->mvcFactoryProvider->create();
        $monitor = method_exists($this->db, 'getMonitor') ? $this->db->getMonitor() : null;

        if (method_exists($this->db, 'setMonitor')) {
            $this->db->setMonitor(null);
        }

        try {
            $this->importCategories($options, $mvcFactory, $result, $output);
            $this->line('', $output);
            $this->importManufacturers($options, $mvcFactory, $result, $output);
            $this->line('', $output);
            $this->importProducts($options, $mvcFactory, $result, $output);
            $this->line('', $output);
            $this->line(
                'Done. Created: ' . $result->created
                . ', updated: ' . $result->updated
                . ', skipped: ' . $result->skipped
                . ', warnings: ' . $result->warnings
                . ', failed: ' . $result->failed . '.',
                $output
            );
        } finally {
            if (method_exists($this->db, 'setMonitor')) {
                $this->db->setMonitor($monitor);
            }
        }

        return $result;
    }

    /**
     * @param   ImportOptions  $options     Import options.
     * @param   MVCFactoryInterface $mvcFactory  MVC factory.
     * @param   ImportResult   $result      Import result.
     * @param   callable|null  $output      Output callback.
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function importCategories(
        ImportOptions $options,
        MVCFactoryInterface $mvcFactory,
        ImportResult $result,
        ?callable $output
    ): void {
        $categories = $this->reader->readRows($options->categoriesJson, 'Categories');

        $this->line('RadicalMart category import', $output);
        $this->line('Categories: ' . count($categories), $output);
        $this->line('JSON: ' . $options->categoriesJson, $output);
        $this->line('Parent ID: ' . $options->parentId, $output);
        $this->line('Mode: ' . $this->modeTitle($options), $output);
        $this->line('', $output);

        foreach ($categories as $index => $source) {
            if (!is_array($source)) {
                $this->line('[skip] Row ' . ($index + 1) . ': invalid category row', $output);
                $result->skipped++;

                continue;
            }

            $title = $this->dataMapper->categoryTitle($source);
            $alias = $this->dataMapper->categoryAlias($source);
            $image = $this->firstFilled($source, 'category_image', 'image');

            if ($title === '') {
                $this->line('[skip] Row ' . ($index + 1) . ': empty title', $output);
                $result->skipped++;

                continue;
            }

            if ($image !== '' && !is_file(JPATH_ROOT . '/images/category_images/' . ltrim($image, '/'))) {
                $this->line('[warn] Image not found for "' . $title . '": images/category_images/' . $image, $output);
                $result->warnings++;
            }

            $existing = $this->lookup->findExistingCategory('category', $alias, $title, $options->parentId);

            if ($existing && !$options->updateExisting) {
                $this->line('[skip] Exists: #' . $existing->id . ' ' . $title, $output);
                $result->skipped++;

                continue;
            }

            $existingId = $existing ? (int) $existing->id : 0;
            $data = $this->dataMapper->categoryData($source, $options->parentId, $existingId);

            if ($options->dryRun) {
                $this->line(($existing ? '[dry-update] ' : '[dry-create] ') . $title . ($alias !== '' ? ' (' . $alias . ')' : ''), $output);

                continue;
            }

            $this->saveService->saveCategoryData($mvcFactory, $data, $title, $existingId, $result, $output);
        }
    }

    /**
     * @param   ImportOptions  $options     Import options.
     * @param   MVCFactoryInterface $mvcFactory  MVC factory.
     * @param   ImportResult   $result      Import result.
     * @param   callable|null  $output      Output callback.
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function importManufacturers(
        ImportOptions $options,
        MVCFactoryInterface $mvcFactory,
        ImportResult $result,
        ?callable $output
    ): void {
        $manufacturers = $this->reader->readRows($options->manufacturersJson, 'Manufacturers');

        $this->line('RadicalMart manufacturer import', $output);
        $this->line('Manufacturers: ' . count($manufacturers), $output);
        $this->line('JSON: ' . $options->manufacturersJson, $output);
        $this->line('Parent ID: ' . $options->parentId, $output);
        $this->line('Mode: ' . $this->modeTitle($options), $output);
        $this->line('', $output);

        foreach ($manufacturers as $index => $source) {
            if (!is_array($source)) {
                $this->line('[skip] Row ' . ($index + 1) . ': invalid manufacturer row', $output);
                $result->skipped++;

                continue;
            }

            $title = $this->firstFilled($source, 'name_ru-RU', 'title');
            $alias = $this->firstFilled($source, 'alias_ru-RU', 'alias');

            if ($title === '') {
                $this->line('[skip] Row ' . ($index + 1) . ': empty title', $output);
                $result->skipped++;

                continue;
            }

            $existing = $this->lookup->findExistingCategory('manufacturer', $alias, $title, $options->parentId);

            if ($existing && !$options->updateExisting) {
                $this->line('[skip] Exists: #' . $existing->id . ' ' . $title, $output);
                $result->skipped++;

                continue;
            }

            $existingId = $existing ? (int) $existing->id : 0;
            $data = $this->dataMapper->manufacturerData($source, $options->parentId, $existingId);

            if ($options->dryRun) {
                $this->line(($existing ? '[dry-update] ' : '[dry-create] ') . $title . ($alias !== '' ? ' (' . $alias . ')' : ''), $output);

                continue;
            }

            $this->saveService->saveCategoryData($mvcFactory, $data, $title, $existingId, $result, $output);
        }
    }

    /**
     * @param   ImportOptions  $options     Import options.
     * @param   MVCFactoryInterface $mvcFactory  MVC factory.
     * @param   ImportResult   $result      Import result.
     * @param   callable|null  $output      Output callback.
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function importProducts(
        ImportOptions $options,
        MVCFactoryInterface $mvcFactory,
        ImportResult $result,
        ?callable $output
    ): void {
        $products = $this->reader->readRows($options->productsJson, 'Products');
        $productCategoryMap = $this->reader->readProductCategoryRelations($options->productCategoriesJson);
        $productImageMap = $this->reader->readProductImageRelations($options->productImagesJson);
        $extraFieldMap = $this->importProductExtraFields($options, $mvcFactory, $output);
        $productExtraFieldMap = $this->reader->readProductExtraFieldRelations($options->productExtraFieldRelationsJson);
        $productColumns = $this->lookup->tableColumns('#__radicalmart_products');
        $categoryMap = $this->lookup->loadImportedCategoryMap('category', 'import_old_category_id');
        $manufacturerMap = $this->lookup->loadImportedCategoryMap('manufacturer', 'import_old_manufacturer_id');

        $this->line('RadicalMart product import', $output);
        $this->line('Products: ' . count($products), $output);
        $this->line('JSON: ' . $options->productsJson, $output);
        $this->line('Product-category relations JSON: ' . $options->productCategoriesJson, $output);
        $this->line('Products with category relations: ' . count($productCategoryMap), $output);
        $this->line('Product-image relations JSON: ' . $options->productImagesJson, $output);
        $this->line('Products with image relations: ' . count($productImageMap), $output);
        $this->line('Extra fields: ' . count($extraFieldMap), $output);
        $this->line('Products with extra field relations: ' . count($productExtraFieldMap), $output);
        $this->line('Image source dir: ' . $options->imageSourceDir, $output);
        $this->line('Image target dir: images/' . $options->imageTargetDir, $output);
        $this->line('Mapped categories: ' . count($categoryMap), $output);
        $this->line('Mapped manufacturers: ' . count($manufacturerMap), $output);
        $this->line('Mode: ' . $this->modeTitle($options), $output);
        $this->line('', $output);

        foreach ($products as $index => $source) {
            if (!is_array($source)) {
                $this->line('[skip] Row ' . ($index + 1) . ': invalid product row', $output);
                $result->skipped++;

                continue;
            }

            $title = $this->firstFilled($source, 'name_ru-RU');
            $alias = $this->firstFilled($source, 'alias_ru-RU');
            $oldProductId = trim((string) ($source['product_id'] ?? ''));
            $oldCategoryId = trim((string) ($source['main_category_id'] ?? ''));
            $oldCategoryIds = $this->dataMapper->productOldCategoryIds($oldProductId, $oldCategoryId, $productCategoryMap);
            $oldManufacturerId = trim((string) ($source['product_manufacturer_id'] ?? ''));
            $imageFiles = $this->imageImportService->productImageFiles($source, $productImageMap);

            if ($title === '') {
                $this->line('[skip] Row ' . ($index + 1) . ': empty product title', $output);
                $result->skipped++;

                continue;
            }

            foreach ($oldCategoryIds as $oldProductCategoryId) {
                if ($oldProductCategoryId !== '0' && !isset($categoryMap[$oldProductCategoryId])) {
                    $this->line('[warn] Category map not found for "' . $title . '": old category #' . $oldProductCategoryId, $output);
                    $result->warnings++;
                }
            }

            if ($oldManufacturerId !== '' && $oldManufacturerId !== '0' && !isset($manufacturerMap[$oldManufacturerId])) {
                $this->line('[warn] Manufacturer map not found for "' . $title . '": old manufacturer #' . $oldManufacturerId, $output);
                $result->warnings++;
            }

            foreach ($imageFiles as $imageFile) {
                $imageStatus = $this->imageImportService->productImageStatus($imageFile, $options->imageSourceDir, $options->imageTargetDir);

                if (!$imageStatus['success']) {
                    $this->line(
                        '[warn] Image not found for "' . $title . '": ' . $imageFile
                        . ' (' . $imageStatus['error'] . ')',
                        $output
                    );
                    $result->warnings++;
                }
            }

            $existing = $this->lookup->findExistingProduct($productColumns, $alias, $title);

            if ($existing && !$options->updateExisting) {
                $this->line('[skip] Exists: #' . $existing->id . ' ' . $title, $output);
                $result->skipped++;

                continue;
            }

            $existingId = $existing ? (int) $existing->id : 0;
            $data = $this->dataMapper->productData(
                $source,
                $categoryMap,
                $productCategoryMap,
                $productImageMap,
                $manufacturerMap,
                $extraFieldMap,
                $productExtraFieldMap,
                $options->imageSourceDir,
                $options->imageTargetDir,
                $existingId
            );

            if ($options->dryRun) {
                $this->line(($existing ? '[dry-update] ' : '[dry-create] ') . $title . ($alias !== '' ? ' (' . $alias . ')' : ''), $output);

                continue;
            }

            $this->saveService->saveProductData($mvcFactory, $data, $title, $existingId, $result, $output);
        }
    }

    /**
     * @param   ImportOptions        $options     Import options.
     * @param   MVCFactoryInterface  $mvcFactory  MVC factory.
     * @param   callable|null        $output      Output callback.
     *
     * @return array<string, array>
     *
     * @since 1.0.0
     */
    private function importProductExtraFields(ImportOptions $options, MVCFactoryInterface $mvcFactory, ?callable $output): array
    {
        $fields = $this->reader->readRows($options->productExtraFieldsJson, 'Product extra fields');
        $values = $this->reader->readProductExtraFieldValues($options->productExtraFieldValuesJson);
        $relations = $this->reader->readProductExtraFieldRelations($options->productExtraFieldRelationsJson);
        $categoryMap = $this->lookup->loadImportedCategoryMap('category', 'import_old_category_id');
        $map = [];

        $this->line('RadicalMart product extra fields import', $output);
        $this->line('Fields: ' . count($fields), $output);
        $this->line('JSON: ' . $options->productExtraFieldsJson, $output);
        $this->line('Values JSON: ' . $options->productExtraFieldValuesJson, $output);
        $this->line('Relations JSON: ' . $options->productExtraFieldRelationsJson, $output);
        $this->line('Mode: ' . $this->modeTitle($options), $output);
        $this->line('', $output);

        foreach ($fields as $index => $source) {
            if (!is_array($source)) {
                $this->line('[skip] Extra field row ' . ($index + 1) . ': invalid row', $output);

                continue;
            }

            $oldFieldId = trim((string) ($source['id'] ?? ''));
            $title = $this->firstFilled($source, 'name_ru-RU');

            if ($oldFieldId === '' || $title === '') {
                $this->line('[skip] Extra field row ' . ($index + 1) . ': empty id or title', $output);

                continue;
            }

            $shippingFieldMap = $this->fieldMapper->shippingFieldMap($source);

            if ($shippingFieldMap !== null) {
                $map[$oldFieldId] = $shippingFieldMap;
                $this->line('[skip] Field ' . $title . ': mapped to product shipping', $output);

                continue;
            }

            $fieldValues = $values[$oldFieldId] ?? [];
            $fieldRelations = $this->fieldMapper->relationValues($oldFieldId, $relations);
            $data = $this->fieldMapper->fieldData($source, $fieldValues, $fieldRelations, $categoryMap);
            $existing = $this->lookup->findExistingField($data['alias']);
            $existingId = $existing ? (int) $existing->id : 0;
            $data['id'] = $existingId;

            if ($options->dryRun) {
                $this->line(($existing ? '[dry-update] ' : '[dry-create] ') . 'Field ' . $title . ' (' . $data['alias'] . ')', $output);
            } else {
                $savedId = $this->saveService->saveFieldData($mvcFactory, $data, $title, $existingId, $output);

                if ($savedId > 0) {
                    $existingId = $savedId;
                }
            }

            if (!$options->dryRun && $existingId === 0) {
                continue;
            }

            $map[$oldFieldId] = [
                'id' => $existingId,
                'alias' => $data['alias'],
                'type' => $data['params']['type'],
                'options' => $data['options'],
                'value_map' => $this->fieldMapper->fieldValueMap($fieldValues, $data['options']),
            ];
        }

        foreach ($this->fieldMapper->directFieldDefinitions() as $mapKey => $definition) {
            $alias = (string) ($definition['alias'] ?? '');
            $title = (string) ($definition['title'] ?? '');
            $existing = $this->lookup->findExistingField($alias);

            if (!$existing) {
                $this->line(
                    '[warn] Service field not found: ' . $title . ' (' . $alias . ')',
                    $output
                );

                continue;
            }

            $params = json_decode((string) ($existing->params ?? ''), true);

            $map[$mapKey] = [
                'id' => (int) $existing->id,
                'alias' => (string) $existing->alias,
                'type' => (string) ($params['type'] ?? $definition['type'] ?? 'text'),
                'options' => [],
                'value_map' => [],
            ];
        }

        return $map;
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

    /**
     * @param   ImportOptions  $options  Import options.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function modeTitle(ImportOptions $options): string
    {
        if ($options->dryRun) {
            return 'dry-run';
        }

        return $options->updateExisting ? 'update existing' : 'create missing';
    }

    /**
     * @param   string         $message  Message.
     * @param   callable|null  $output   Output callback.
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function line(string $message, ?callable $output): void
    {
        if ($output !== null) {
            $output($message);
        }
    }
}

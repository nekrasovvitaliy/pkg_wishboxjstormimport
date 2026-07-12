<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\Console\WishboxJsToRmImport\Console\Command;

use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\Exception\ExecutionFailureException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WishboxJsToRmImportLibrary\Dto\ImportOptions;
use WishboxJsToRmImportLibrary\Service\CatalogImportService;

defined('_JEXEC') or die;

/**
 * Imports jShopping catalog data into RadicalMart.
 *
 * @since 1.0.0
 */
final class ImportCommand extends AbstractCommand
{
    /**
     * @var string
     *
     * @since 1.0.0
     */
    protected static $defaultName = 'wishboxjstormimport:import';

    /**
     * @param CatalogImportService $importService Import service.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly CatalogImportService $importService)
    {
        parent::__construct();
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    protected function configure(): void
    {
        $this->setDescription('Import jShopping catalog data into RadicalMart');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show actions without saving data');
        $this->addOption('update', null, InputOption::VALUE_NONE, 'Update existing records');
        $this->addOption('parent', null, InputOption::VALUE_REQUIRED, 'RadicalMart parent category ID', '1');
        $this->addOption('source-dir', null, InputOption::VALUE_REQUIRED, 'Directory with JSON files', JPATH_ROOT);
        $this->addOption('categories-json', null, InputOption::VALUE_REQUIRED, 'Categories JSON file', 'jshopping_categories.json');
        $this->addOption('manufacturers-json', null, InputOption::VALUE_REQUIRED, 'Manufacturers JSON file', 'jshopping_manufacturers.json');
        $this->addOption('products-json', null, InputOption::VALUE_REQUIRED, 'Products JSON file', 'jshopping_products.json');
        $this->addOption('product-categories-json', null, InputOption::VALUE_REQUIRED, 'Product-category relations JSON file', 'jshopping_products_to_categories.json');
        $this->addOption('product-images-json', null, InputOption::VALUE_REQUIRED, 'Product-image relations JSON file', 'jshopping_products_images.json');
        $this->addOption('product-extra-fields-json', null, InputOption::VALUE_REQUIRED, 'Product extra fields JSON file', 'jshopping_products_extra_fields.json');
        $this->addOption('product-extra-field-values-json', null, InputOption::VALUE_REQUIRED, 'Product extra field values JSON file', 'jshopping_products_extra_field_values.json');
        $this->addOption('product-extra-field-relations-json', null, InputOption::VALUE_REQUIRED, 'Product extra field relations JSON file', 'jshopping_products_to_extra_fields.json');
        $this->addOption('image-source-dir', null, InputOption::VALUE_REQUIRED, 'Product image source directory', 'img_products');
        $this->addOption('image-target-dir', null, InputOption::VALUE_REQUIRED, 'Product image target directory inside images/', 'img_products');
    }

    /**
     * @param InputInterface $input Console input.
     * @param OutputInterface $output Console output.
     *
     * @return integer
     *
     * @since 1.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        if (!ini_set('memory_limit', '1024M'))
        {
            throw new \Exception('ini_set return false');
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('WishboxJsToRmImport');

        $sourceDir = rtrim((string)$input->getOption('source-dir'), '/');
        $options = new ImportOptions();
        $options->dryRun = (bool)$input->getOption('dry-run');
        $options->updateExisting = (bool)$input->getOption('update');
        $options->parentId = (int)$input->getOption('parent');
        $options->categoriesJson = $this->jsonPath($sourceDir, (string)$input->getOption('categories-json'));
        $options->manufacturersJson = $this->jsonPath($sourceDir, (string)$input->getOption('manufacturers-json'));
        $options->productsJson = $this->jsonPath($sourceDir, (string)$input->getOption('products-json'));
        $options->productCategoriesJson = $this->jsonPath($sourceDir, (string)$input->getOption('product-categories-json'));
        $options->productImagesJson = $this->jsonPath($sourceDir, (string)$input->getOption('product-images-json'));
        $options->productExtraFieldsJson = $this->jsonPath($sourceDir, (string)$input->getOption('product-extra-fields-json'));
        $options->productExtraFieldValuesJson = $this->jsonPath($sourceDir, (string)$input->getOption('product-extra-field-values-json'));
        $options->productExtraFieldRelationsJson = $this->jsonPath($sourceDir, (string)$input->getOption('product-extra-field-relations-json'));
        $options->imageSourceDir = trim((string)$input->getOption('image-source-dir'), '/');
        $options->imageTargetDir = trim((string)$input->getOption('image-target-dir'), '/');

        $result = $this->importService->import(
            $options,
            static function (string $line) use ($io): void {
                $io->writeln($line);
            }
        );

        return $result->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param string $sourceDir Source directory.
     * @param string $file File path.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function jsonPath(string $sourceDir, string $file): string
    {
        if (str_starts_with($file, '/')) {
            return $file;
        }

        return $sourceDir . '/' . ltrim($file, '/');
    }
}

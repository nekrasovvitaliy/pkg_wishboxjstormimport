<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\RadicalMart\Administrator\Model\CategoryModel;
use Joomla\Component\RadicalMart\Administrator\Model\FieldModel;
use Joomla\Component\RadicalMart\Administrator\Model\ProductModel;
use RuntimeException;
use Throwable;
use WishboxJsToRmImportLibrary\Dto\ImportResult;

defined('_JEXEC') or die;

/**
 * Persists prepared RadicalMart payloads through Joomla models.
 *
 * @since 1.0.0
 */
final readonly class RadicalMartSaveService
{
    /**
     * @param   MVCFactoryInterface  $mvcFactory  MVC factory.
     * @param   array                $data        Field data.
     * @param   string               $title       Title.
     * @param   integer              $existingId  Existing ID.
     * @param   callable|null        $output      Output callback.
     *
     * @return integer
     *
     * @since 1.0.0
     */
    public function saveFieldData(
        MVCFactoryInterface $mvcFactory,
        array $data,
        string $title,
        int $existingId,
        ?callable $output
    ): int {
        try {
            /** @var FieldModel $fieldModel */
            $fieldModel = $mvcFactory->createModel('Field', 'Administrator', ['ignore_request' => true]);

            if ($fieldModel === null) {
                throw new RuntimeException(
                    'Unable to create RadicalMart FieldModel. Check administrator/components/com_radicalmart/src/Model/FieldModel.php'
                );
            }

            $fieldModel->setState('save.task', 'save');
            $savedId = $fieldModel->save($data);

            if ($savedId === false) {
                $this->line('[fail] Field ' . $title . ': ' . $this->modelError($fieldModel), $output);

                return 0;
            }

            if ($existingId > 0) {
                $this->line('[update] Field #' . $existingId . ' ' . $title, $output);

                return $existingId;
            }

            $this->line('[create] Field #' . (int) $savedId . ' ' . $title, $output);

            return (int) $savedId;
        } catch (Throwable $e) {
            $this->line('[fail] Field ' . $title . ': ' . $e->getMessage(), $output);

            return 0;
        }
    }

    /**
     * @param   MVCFactoryInterface  $mvcFactory  MVC factory.
     * @param   array                $data        Category data.
     * @param   string               $title       Title.
     * @param   integer              $existingId  Existing ID.
     * @param   ImportResult         $result      Import result.
     * @param   callable|null        $output      Output callback.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function saveCategoryData(
        MVCFactoryInterface $mvcFactory,
        array $data,
        string $title,
        int $existingId,
        ImportResult $result,
        ?callable $output
    ): void {
        try {
            /** @var CategoryModel $categoryModel */
            $categoryModel = $mvcFactory->createModel('Category', 'Administrator', ['ignore_request' => true]);

            if ($categoryModel === null) {
                throw new RuntimeException(
                    'Unable to create RadicalMart CategoryModel. Check administrator/components/com_radicalmart/src/Model/CategoryModel.php'
                );
            }

            $categoryModel->setState('save.task', 'save');
            $savedId = $categoryModel->save($data);

            if ($savedId === false) {
                $this->line('[fail] ' . $title . ': ' . $this->modelError($categoryModel), $output);
                $result->failed++;

                return;
            }

            if ($existingId > 0) {
                $this->line('[update] #' . $existingId . ' ' . $title, $output);
                $result->updated++;

                return;
            }

            $this->line('[create] #' . (int) $savedId . ' ' . $title, $output);
            $result->created++;
        } catch (Throwable $e) {
            $this->line('[fail] ' . $title . ': ' . $e->getMessage(), $output);
            $result->failed++;
        }
    }

    /**
     * @param   MVCFactoryInterface  $mvcFactory  MVC factory.
     * @param   array                $data        Product data.
     * @param   string               $title       Title.
     * @param   integer              $existingId  Existing ID.
     * @param   ImportResult         $result      Import result.
     * @param   callable|null        $output      Output callback.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function saveProductData(
        MVCFactoryInterface $mvcFactory,
        array $data,
        string $title,
        int $existingId,
        ImportResult $result,
        ?callable $output
    ): void {
        try {
            /** @var ProductModel $productModel */
            $productModel = $mvcFactory->createModel('Product', 'Administrator', ['ignore_request' => true]);

            if ($productModel === null) {
                throw new RuntimeException(
                    'Unable to create RadicalMart ProductModel. Check administrator/components/com_radicalmart/src/Model/ProductModel.php'
                );
            }

            $productModel->setState('save.task', 'save');
            $savedId = $productModel->save($data);

            if ($savedId === false) {
                $this->line('[fail] ' . $title . ': ' . $this->modelError($productModel), $output);
                $result->failed++;

                return;
            }

            if ($existingId > 0) {
                $this->line('[update] #' . $existingId . ' ' . $title, $output);
                $result->updated++;

                return;
            }

            $this->line('[create] #' . (int) $savedId . ' ' . $title, $output);
            $result->created++;
        } catch (Throwable $e) {
            $this->line('[fail] ' . $title . ': ' . $e->getMessage(), $output);
            $result->failed++;
        }
    }

    /**
     * @param   object  $model  Model.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function modelError(object $model): string
    {
        return method_exists($model, 'getError') ? (string) $model->getError() : 'Unknown error';
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

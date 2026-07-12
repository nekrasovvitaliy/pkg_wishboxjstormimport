<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\CMS\Http\HttpFactory;
use Throwable;

defined('_JEXEC') or die;

/**
 * Builds Joomla image field values for imported RadicalMart records.
 *
 * @since 1.0.0
 */
final readonly class ImageImportService
{
    /**
     * @var string
     *
     * @since 1.0.0
     */
    private const SOURCE_PRODUCT_IMAGE_URL = 'https://playandlearn.ru/components/com_jshopping/files/img_products/full_';

    /**
     * @param   string  $image  Image file.
     *
     * @return array{success: bool, error: string}
     *
     * @since 1.0.0
     */
    public function categoryImageValue(string $image): string
    {
        $image = ltrim(trim($image), '/');

        if ($image === '') {
            return '';
        }

        return $this->joomlaImageValue('category_images', $image, 400, 400);
    }

    /**
     * @param   array   $source           Source row.
     * @param   array   $productImageMap  Product image map.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function productImageFiles(array $source, array $productImageMap): array
    {
        $oldProductId = trim((string) ($source['product_id'] ?? ''));
        $images = [];
        $mainImage = trim((string) ($source['image'] ?? ''));

        if ($mainImage !== '') {
            $images[] = $mainImage;
        }

        if (isset($productImageMap[$oldProductId])) {
            foreach ($productImageMap[$oldProductId] as $relation) {
                $image = trim((string) ($relation['image_name'] ?? ''));

                if ($image !== '') {
                    $images[] = $image;
                }
            }
        }

        $files = [];

        foreach ($images as $image) {
            $file = $this->productImageFileName($image);

            if ($file !== '') {
                $files[] = $file;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param   array   $source           Source row.
     * @param   array   $productImageMap  Product image map.
     * @param   string  $imageSourceDir   Image source dir.
     * @param   string  $imageTargetDir   Image target dir.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function productImageValues(array $source, array $productImageMap, string $imageSourceDir, string $imageTargetDir): array
    {
        $values = [];

        foreach ($this->productImageFiles($source, $productImageMap) as $imageFile) {
            if (!$this->ensureProductImage($imageFile, $imageSourceDir, $imageTargetDir)['success']) {
                continue;
            }

            $imageValue = $this->productImageValue($imageFile, $imageTargetDir);

            if ($imageValue !== '') {
                $values[] = $imageValue;
            }
        }

        return $values;
    }

    /**
     * @param   string  $imageFile       Image file.
     * @param   string  $imageSourceDir  Image source dir.
     * @param   string  $imageTargetDir  Image target dir.
     *
     * @return array{success: bool, error: string}
     *
     * @since 1.0.0
     */
    public function productImageExists(string $imageFile, string $imageSourceDir, string $imageTargetDir): bool
    {
        return $this->productImageStatus($imageFile, $imageSourceDir, $imageTargetDir)['success'];
    }

    /**
     * @param   string  $imageFile       Image file.
     * @param   string  $imageSourceDir  Image source dir.
     * @param   string  $imageTargetDir  Image target dir.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function productImageStatus(string $imageFile, string $imageSourceDir, string $imageTargetDir): array
    {
        return $this->ensureProductImage($imageFile, $imageSourceDir, $imageTargetDir);
    }

    /**
     * @param   string  $imageFile       Image file.
     * @param   string  $imageTargetDir  Image target dir.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function productImageValue(string $imageFile, string $imageTargetDir): string
    {
        $imageFile = ltrim(trim($imageFile), '/');

        if ($imageFile === '') {
            return '';
        }

        return $this->joomlaImageValue(trim($imageTargetDir, '/'), $imageFile, 800, 800);
    }

    /**
     * @param   string   $targetDir      Target directory inside images.
     * @param   string   $imageFile      Image file.
     * @param   integer  $defaultWidth   Default width.
     * @param   integer  $defaultHeight  Default height.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function joomlaImageValue(string $targetDir, string $imageFile, int $defaultWidth, int $defaultHeight): string
    {
        $relativePath = 'images/' . $targetDir . '/' . $imageFile;
        $localPath = JPATH_ROOT . '/' . $relativePath;
        $width = $defaultWidth;
        $height = $defaultHeight;

        if (is_file($localPath)) {
            $size = @getimagesize($localPath);

            if ($size !== false) {
                $width = (int) $size[0];
                $height = (int) $size[1];
            }
        }

        return $relativePath
            . '#joomlaImage://local-images/'
            . rawurlencode($targetDir)
            . '/'
            . rawurlencode($imageFile)
            . '?width=' . $width
            . '&height=' . $height;
    }

    /**
     * @param   string  $image  Image.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function productImageFileName(string $image): string
    {
        $image = ltrim(trim($image), '/');

        if ($image === '') {
            return '';
        }

        $path = parse_url($image, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $image = $path;
        }

        $image = basename($image);

        if ($image === '') {
            return '';
        }

        return str_starts_with($image, 'full_') ? $image : 'full_' . $image;
    }

    /**
     * @param   string  $imageFile       Image file.
     * @param   string  $imageSourceDir  Image source dir.
     * @param   string  $imageTargetDir  Image target dir.
     *
     * @return array{success: bool, error: string}
     *
     * @since 1.0.0
     */
    private function ensureProductImage(string $imageFile, string $imageSourceDir, string $imageTargetDir): array
    {
        $imageFile = $this->productImageFileName($imageFile);

        if ($imageFile === '') {
            return [
                'success' => false,
                'error' => 'empty image file',
            ];
        }

        $targetDir = JPATH_ROOT . '/images/' . trim($imageTargetDir, '/');
        $targetPath = $targetDir . '/' . $imageFile;

        if (is_file($targetPath)) {
            return [
                'success' => true,
                'error' => '',
            ];
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return [
                'success' => false,
                'error' => 'unable to create target directory: ' . $targetDir,
            ];
        }

        foreach ($this->localProductImagePaths($imageFile, $imageSourceDir, $imageTargetDir) as $localPath) {
            if (is_file($localPath) && @copy($localPath, $targetPath)) {
                return [
                    'success' => true,
                    'error' => '',
                ];
            }
        }

        return $this->downloadProductImage($imageFile, $targetPath);
    }

    /**
     * @param   string  $imageFile       Image file.
     * @param   string  $imageSourceDir  Image source dir.
     * @param   string  $imageTargetDir  Image target dir.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    private function localProductImagePaths(string $imageFile, string $imageSourceDir, string $imageTargetDir): array
    {
        $sourceDir = trim($imageSourceDir, '/');
        $targetDir = trim($imageTargetDir, '/');

        return array_values(
            array_unique(
                [
                    JPATH_ROOT . '/' . $sourceDir . '/' . $imageFile,
                    JPATH_ROOT . '/images/' . $sourceDir . '/' . $imageFile,
                    JPATH_ROOT . '/images/' . $targetDir . '/' . $imageFile,
                ]
            )
        );
    }

    /**
     * @param   string  $imageFile   Image file.
     * @param   string  $targetPath  Target path.
     *
     * @return boolean
     *
     * @since 1.0.0
     */
    private function downloadProductImage(string $imageFile, string $targetPath): array
    {
        $sources = $this->productImageSourceUrls($imageFile);
        $lastError = 'download failed';

        foreach ($sources as $source) {
            $contents = $this->downloadProductImageContents($source);

            if ($contents === '') {
                continue;
            }

            if (@getimagesizefromstring($contents) === false) {
                $lastError = 'downloaded file is not a valid image: ' . $source;

                continue;
            }

            if (@file_put_contents($targetPath, $contents) === false) {
                return [
                    'success' => false,
                    'error' => 'unable to write target file: ' . $targetPath,
                ];
            }

            if (@getimagesize($targetPath) === false) {
                @unlink($targetPath);

                $lastError = 'saved file is not a valid image: ' . $targetPath;

                continue;
            }

            return [
                'success' => true,
                'error' => '',
            ];
        }

        return [
            'success' => false,
            'error' => $lastError . ': ' . implode(', ', $sources),
        ];
    }

    /**
     * @param   string  $imageFile  Image file.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    private function productImageSourceUrls(string $imageFile): array
    {
        $sourceFile = substr($imageFile, 5);
        $sourceFiles = [$sourceFile];

        if (str_ends_with($sourceFile, '.JPG')) {
            $sourceFiles[] = substr($sourceFile, 0, -4) . '.jpg';
        } elseif (str_ends_with($sourceFile, '.jpg')) {
            $sourceFiles[] = substr($sourceFile, 0, -4) . '.JPG';
        }

        return array_map(
            static fn(string $file): string => self::SOURCE_PRODUCT_IMAGE_URL . rawurlencode($file),
            array_values(array_unique($sourceFiles))
        );
    }

    /**
     * @param   string  $source  Source URL.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function downloadProductImageContents(string $source): string
    {
        $contents = $this->downloadProductImageWithJoomlaHttp($source);

        if ($contents !== '') {
            return $contents;
        }

        $contents = $this->downloadProductImageWithCurl($source);

        if ($contents !== '') {
            return $contents;
        }

        return $this->downloadProductImageWithStream($source);
    }

    /**
     * @param   string  $source  Source URL.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function downloadProductImageWithJoomlaHttp(string $source): string
    {
        try {
            $response = HttpFactory::getHttp()
                ->get($source, ['User-Agent' => 'Mozilla/5.0'], 20);
        } catch (Throwable) {
            return '';
        }

        if ($response->code < 200 || $response->code >= 300 || $response->body === '') {
            return '';
        }

        return $response->body;
    }

    /**
     * @param   string  $source  Source URL.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function downloadProductImageWithCurl(string $source): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        $curl = curl_init($source);

        if ($curl === false) {
            return '';
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]
        );

        $contents = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($contents) || $contents === '' || $status < 200 || $status >= 300) {
            return '';
        }

        return $contents;
    }

    /**
     * @param   string  $source  Source URL.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function downloadProductImageWithStream(string $source): string
    {
        $context = stream_context_create(
            [
                'http' => [
                    'follow_location' => 1,
                    'header' => "User-Agent: Mozilla/5.0\r\n",
                    'ignore_errors' => true,
                    'timeout' => 20,
                ],
            ]
        );
        $contents = @file_get_contents($source, false, $context);

        return is_string($contents) ? $contents : '';
    }
}

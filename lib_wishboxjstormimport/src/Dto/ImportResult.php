<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Dto;

defined('_JEXEC') or die;

/**
 * Catalog import result counters.
 *
 * @since 1.0.0
 */
final class ImportResult
{
    /**
     * @since 1.0.0
     */
    public int $created = 0;

    /**
     * @since 1.0.0
     */
    public int $updated = 0;

    /**
     * @since 1.0.0
     */
    public int $skipped = 0;

    /**
     * @since 1.0.0
     */
    public int $warnings = 0;

    /**
     * @since 1.0.0
     */
    public int $failed = 0;

    /**
     * @return boolean
     *
     * @since 1.0.0
     */
    public function isSuccess(): bool
    {
        return $this->failed === 0;
    }
}

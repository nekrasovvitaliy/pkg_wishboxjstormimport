<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\Console\WishboxJsToRmImport\Extension;

use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Console\WishboxJsToRmImport\Console\Command\ImportCommand;
use WishboxJsToRmImportLibrary\Service\CatalogImportService;

defined('_JEXEC') or die;

/**
 * Console plugin for WishboxJsToRmImport.
 *
 * @since 1.0.0
 */
final class WishboxJsToRmImport extends CMSPlugin implements SubscriberInterface
{
    /**
     * @param   CatalogImportService  $importService  Import service.
     *
     * @since 1.0.0
     */
    public function __construct(
        &$subject,
        array $config,
        private readonly CatalogImportService $importService
    ) {
        parent::__construct($subject, $config);
    }

    /**
     * @return string[]
     *
     * @since 1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
        ];
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function registerCommands(): void
    {
        /** @var ConsoleApplication $app */
        $app = $this->getApplication();
        $app->addCommand(new ImportCommand($this->importService));
    }
}

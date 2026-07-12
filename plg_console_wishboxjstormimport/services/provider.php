<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Console\WishboxJsToRmImport\Extension\WishboxJsToRmImport;
use WishboxJsToRmImportLibrary\Service\CatalogImportService;

defined('_JEXEC') or die;

return new class implements ServiceProviderInterface {
    /**
     * @param   Container  $container  DI container.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            CatalogImportService::class,
            static fn(Container $container): CatalogImportService => new CatalogImportService(
                $container->get(DatabaseInterface::class),
                $container->get(FormFactoryInterface::class),
                $container->get(DispatcherInterface::class),
                $container->get(CacheControllerFactoryInterface::class),
                $container->get(UserFactoryInterface::class),
                $container->get(MailerFactoryInterface::class)
            )
        );

        $container->set(
            PluginInterface::class,
            static function (Container $container): PluginInterface {
                $dispatcher = $container->get(DispatcherInterface::class);
                $config = (array) PluginHelper::getPlugin('console', 'wishboxjstormimport');

                $plugin = new WishboxJsToRmImport(
                    $dispatcher,
                    $config,
                    $container->get(CatalogImportService::class)
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};

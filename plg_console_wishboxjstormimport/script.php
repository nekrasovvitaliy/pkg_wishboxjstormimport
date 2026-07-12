<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

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
            InstallerScriptInterface::class,
            new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
                /**
                 * @var AdministratorApplication
                 *
                 * @since 1.0.0
                 */
                protected AdministratorApplication $app;

                /**
                 * @var DatabaseDriver
                 *
                 * @since 1.0.0
                 */
                protected DatabaseDriver $db;

                /**
                 * @param   AdministratorApplication  $app  Application.
                 *
                 * @since 1.0.0
                 */
                public function __construct(AdministratorApplication $app)
                {
                    $this->app = $app;
                    $this->db = Factory::getContainer()->get(DatabaseDriver::class);
                }

                /**
                 * @param   InstallerAdapter  $adapter  Installer adapter.
                 *
                 * @return boolean
                 *
                 * @since 1.0.0
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    $this->enablePlugin($adapter);

                    return true;
                }

                /**
                 * @param   InstallerAdapter  $adapter  Installer adapter.
                 *
                 * @return boolean
                 *
                 * @since 1.0.0
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    $this->enablePlugin($adapter);

                    return true;
                }

                /**
                 * @param   InstallerAdapter  $adapter  Installer adapter.
                 *
                 * @return boolean
                 *
                 * @since 1.0.0
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @param   string            $type     Install type.
                 * @param   InstallerAdapter  $adapter  Installer adapter.
                 *
                 * @return boolean
                 *
                 * @since 1.0.0
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @param   string            $type     Install type.
                 * @param   InstallerAdapter  $adapter  Installer adapter.
                 *
                 * @return boolean
                 *
                 * @since 1.0.0
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @param   InstallerAdapter  $adapter  Installer adapter.
                 *
                 * @return void
                 *
                 * @since 1.0.0
                 */
                protected function enablePlugin(InstallerAdapter $adapter): void
                {
                    $plugin = new stdClass;
                    $plugin->type = 'plugin';
                    $plugin->element = $adapter->getElement();
                    $plugin->folder = (string) $adapter->getParent()->manifest->attributes()['group'];
                    $plugin->enabled = 1;

                    $this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
                }
            }
        );
    }
};

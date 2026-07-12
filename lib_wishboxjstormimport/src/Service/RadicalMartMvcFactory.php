<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Throwable;

defined('_JEXEC') or die;

/**
 * Creates a RadicalMart MVC factory for Joomla CLI import contexts.
 *
 * @since 1.0.0
 */
final readonly class RadicalMartMvcFactory
{
    /**
     * @param   DatabaseInterface                $db                      Database.
     * @param   FormFactoryInterface             $formFactory             Form factory.
     * @param   DispatcherInterface              $dispatcher              Dispatcher.
     * @param   CacheControllerFactoryInterface  $cacheControllerFactory  Cache controller factory.
     * @param   UserFactoryInterface             $userFactory             User factory.
     * @param   MailerFactoryInterface           $mailerFactory           Mailer factory.
     *
     * @since 1.0.0
     */
    public function __construct(
        private DatabaseInterface $db,
        private FormFactoryInterface $formFactory,
        private DispatcherInterface $dispatcher,
        private CacheControllerFactoryInterface $cacheControllerFactory,
        private UserFactoryInterface $userFactory,
        private MailerFactoryInterface $mailerFactory
    ) {
    }

    /**
     * @return MVCFactoryInterface
     *
     * @since 1.0.0
     */
    public function create(): MVCFactoryInterface
    {
        try {
            $app = Factory::getApplication();

            if (method_exists($app, 'bootComponent')) {
                $radicalMartComponent = $app->bootComponent('com_radicalmart');

                if ($radicalMartComponent instanceof MVCFactoryServiceInterface) {
                    return $radicalMartComponent->getMVCFactory();
                }
            }
        } catch (Throwable) {
            // Fall back to an explicit MVC factory for CLI/import contexts.
        }

        $mvcFactory = new MVCFactory('Joomla\\Component\\RadicalMart');
        $mvcFactory->setFormFactory($this->formFactory);
        $mvcFactory->setDispatcher($this->dispatcher);
        $mvcFactory->setDatabase($this->db);
        $mvcFactory->setCacheControllerFactory($this->cacheControllerFactory);
        $mvcFactory->setUserFactory($this->userFactory);
        $mvcFactory->setMailerFactory($this->mailerFactory);

        return $mvcFactory;
    }
}

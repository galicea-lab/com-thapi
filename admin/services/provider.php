<?php
// admin/services/provider.php
defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use ThApi\Component\ThApi\Administrator\Extension\ThApiComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// WAŻNE: Dodaj to dla rejestracji własnych pól
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;

return new class implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('\\ThApi\\Component\\ThApi'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\ThApi\\Component\\ThApi'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new ThApiComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                
                return $component;
            }
        );

        // REJESTRACJA WŁASNEGO POLA - to jest kluczowe!
        $container->extend(FormFactoryInterface::class, function ($formFactory, Container $container) {
            // Dodajemy nasze własne pole do formularza
            Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_thapi/src/Field');
            return $formFactory;
        });
    }
};
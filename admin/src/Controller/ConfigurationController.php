<?php
// admin/src/Controller/ConfigurationController.php

namespace ThApi\Component\ThApi\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class ConfigurationController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', 'configuration');
        $this->input->set('view', $view);
        
        return parent::display($cachable, $urlparams);
    }

    public function edit()
    {
        // Sprawdź uprawnienia
        if (!Factory::getUser()->authorise('core.admin', 'com_thapi')) {
            $this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_thapi&view=dashboard', false));
            return false;
        }

        $this->input->set('view', 'configuration');
        $this->input->set('layout', 'edit');
        
        return parent::display();
    }

    public function save()
    {
        return $this->processSave('save');
    }

    public function apply()
    {
        return $this->processSave('apply');
    }

    protected function processSave($taskType)
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Sprawdź uprawnienia
        if (!Factory::getUser()->authorise('core.admin', 'com_thapi')) {
            $this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_thapi&view=dashboard', false));
            return false;
        }

        $app = Factory::getApplication();
        $data = $this->input->post->get('jform', array(), 'array');
        
        // Pobierz model
        $model = $this->getModel('Configuration');

        // Sprawdź czy model został poprawnie załadowany
        if (!$model) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false));
            return false;
        }

        // Walidacja formularza
        $form = $model->getForm($data, false);
        
        if (!$form) {
            $this->setMessage($model->getError(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false));
            return false;
        }

        $validData = $model->validate($form, $data);

        // Sprawdź błędy walidacji
        if ($validData === false) {
            $errors = $model->getErrors();
            foreach ($errors as $error) {
                if ($error instanceof \Exception) {
                    $app->enqueueMessage($error->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($error, 'warning');
                }
            }

            $app->setUserState('com_thapi.config.data', $data);
            $this->setRedirect(Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false));
            return false;
        }

        // Attempt to save the data.
        if ($model->save($validData)) {
            $this->setMessage(Text::_('COM_THAPI_CONFIG_SAVED'));
            
            // Wyczyść dane z sesji
            $app->setUserState('com_thapi.config.data', null);
            
            if ($taskType === 'apply') {
                $this->setRedirect(Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false));
            } else {
                $this->setRedirect(Route::_('index.php?option=com_thapi&view=dashboard', false));
            }
        } else {
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');
            $app->setUserState('com_thapi.config.data', $validData);
            $this->setRedirect(Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false));
        }

        return true;
    }

    public function cancel()
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Wyczyść dane z sesji
        Factory::getApplication()->setUserState('com_thapi.config.data', null);
        
        $this->setRedirect(Route::_('index.php?option=com_thapi&view=dashboard', false));
        return true;
    }

    /**
     * Pobierz model z obsługą błędów
     */
    public function getModel($name = 'Configuration', $prefix = 'Administrator', $config = array())
    {
        try {
            $model = parent::getModel($name, $prefix, $config);
            return $model;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Błąd ładowania modelu: ' . $e->getMessage(), 'error');
            return false;
        }
    }
}
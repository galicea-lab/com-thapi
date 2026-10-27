<?php
// admin/src/Controller/ConfigurationController.php

namespace ThApi\Component\ThApi\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Controller for the component configuration screen.
 *
 * The dashboard has been removed, so every redirect points back
 * to the configuration view instead of the dashboard view.
 */
class ConfigurationController extends BaseController
{
    /**
     * URL of the configuration form.
     *
     * @return  string
     */
    protected function getFormUrl()
    {
        return Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false);
    }

    /**
     * Make sure the current user may change the component options.
     *
     * @return  boolean
     */
    protected function checkAdmin()
    {
        if ($this->app->getIdentity()->authorise('core.admin', 'com_thapi')) {
            return true;
        }

        $this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
        $this->setRedirect(Route::_('index.php', false));

        return false;
    }

    public function display($cachable = false, $urlparams = [])
    {
        $this->input->set('view', 'configuration');

        return parent::display($cachable, $urlparams);
    }

    public function edit()
    {
        if (!$this->checkAdmin()) {
            return false;
        }

        $this->input->set('view', 'configuration');
        $this->input->set('layout', 'edit');

        return parent::display();
    }

    public function save()
    {
        return $this->processSave();
    }

    public function apply()
    {
        return $this->processSave();
    }

    /**
     * Validates and stores the submitted options.
     *
     * @return  boolean
     */
    protected function processSave()
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        if (!$this->checkAdmin()) {
            return false;
        }

        $data = $this->input->post->get('jform', [], 'array');

        /** @var \ThApi\Component\ThApi\Administrator\Model\ConfigurationModel $model */
        $model = $this->getModel('Configuration');

        if (!$model) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'), 'error');
            $this->setRedirect($this->getFormUrl());

            return false;
        }

        // Validate the submitted form.
        $form = $model->getForm($data, false);

        if (!$form) {
            $this->setMessage($model->getError(), 'error');
            $this->setRedirect($this->getFormUrl());

            return false;
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage($error instanceof \Exception ? $error->getMessage() : $error, 'warning');
            }

            $this->app->setUserState('com_thapi.config.data', $data);
            $this->setRedirect($this->getFormUrl());

            return false;
        }

        if ($model->save($validData)) {
            $this->setMessage(Text::_('COM_THAPI_CONFIG_SAVED'));
            $this->app->setUserState('com_thapi.config.data', null);
        } else {
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');
            $this->app->setUserState('com_thapi.config.data', $validData);
        }

        // Both "save" and "apply" stay on the configuration screen.
        $this->setRedirect($this->getFormUrl());

        return true;
    }

    public function cancel()
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $this->app->setUserState('com_thapi.config.data', null);

        // The dashboard no longer exists – "Close" returns to the administrator home page.
        $this->setRedirect(Route::_('index.php', false));

        return true;
    }
}

<?php
// admin/src/Controller/DisplayController.php

namespace ThApi\Component\ThApi\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Default component controller.
 *
 * The dashboard view has been removed – the configuration screen
 * is now the default (and only) view of the component.
 */
class DisplayController extends BaseController
{
    protected $default_view = 'configuration';

    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->getCmd('view', $this->default_view);

        // "dashboard" no longer exists – send the user to the configuration view.
        if ($view === 'dashboard') {
            $this->setRedirect(
                Route::_('index.php?option=com_thapi&view=' . $this->default_view . '&layout=edit', false)
            );

            return $this;
        }

        if ($view === '') {
            $view = $this->default_view;
        }

        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }
}

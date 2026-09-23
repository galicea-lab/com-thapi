<?php
// admin/src/Controller/DashboardController.php

namespace ThApi\Component\ThApi\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

class DashboardController extends BaseController
{
    public function display($cachable = false, $urlparams = array())
    {
        $this->app = Factory::getApplication();
        
        // Sprawdź uprawnienia
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_thapi')) {
            throw new \Exception('JERROR_ALERTNOAUTHOR', 403);
        }

        $view = $this->getView('Dashboard', 'html');
        return parent::display($cachable, $urlparams);
    }

    public function getStats()
    {
        // API endpoint dla statystyk
        $model = $this->getModel('Dashboard');
        $stats = $model->getStats();
        
        echo json_encode($stats);
        $this->app->close();
    }
}
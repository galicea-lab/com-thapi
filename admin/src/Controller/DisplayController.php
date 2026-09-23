<?php
// admin/src/Controller/DisplayController.php
namespace ThApi\Component\ThApi\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') or die;

class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', $this->default_view);
        
        return parent::display($cachable, $urlparams);
    }
}
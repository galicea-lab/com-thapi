<?php
// admin/src/View/Dashboard/HtmlView.php

namespace ThApi\Component\ThApi\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    protected $stats;
    protected $config;

    public function display($tpl = null)
    {
        $this->stats = $this->get('Stats');
        $this->config = $this->get('Config', 'Configuration');

        $this->addToolbar();
        $this->prepareDocument();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_THAPI_DASHBOARD'), 'dashboard');
        
        if (Factory::getUser()->authorise('core.admin', 'com_thapi')) {
            ToolbarHelper::preferences('com_thapi');
        }
    }

    protected function prepareDocument()
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('jquery');
    }

    public function getExportUrl()
    {
        $baseUrl = Uri::root() . 'index.php';
        $params = [
            'option' => 'com_thapi',
            'task' => 'api.exportJson',
            'format' => 'json'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }

    public function getUpdatesUrl()
    {
        $baseUrl = Uri::root() . 'administrator/index.php';
        $params = [
            'option' => 'com_thapi',
            'task' => 'api.checkUpdates',
            'format' => 'json'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }

    public function getCategoriesUrl()
    {
        $baseUrl = Uri::root() . 'administrator/index.php';
        $params = [
            'option' => 'com_thapi',
            'task' => 'api.getCategories',
            'format' => 'json'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }

    // Prosta metoda do pobierania kategorii - bez modelu Export
    public function getCategoriesList()
    {
        $db = Factory::getDbo();
        
        try {
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'description'])
                ->from('#__categories')
                ->where('extension = ' . $db->quote('com_content'))
                ->where('published = 1')
                ->order('title ASC')
                ->setLimit(20);
            
            return $db->setQuery($query)->loadObjectList();
        } catch (\Exception $e) {
            // Fallback - przykładowe kategorie
            return [
                (object)['id' => 1, 'title' => 'Uncategorised', 'description' => ''],
                (object)['id' => 2, 'title' => 'Blog', 'description' => ''],
                (object)['id' => 3, 'title' => 'News', 'description' => '']
            ];
        }
    }
}
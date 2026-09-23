<?php
// admin/src/View/Configuration/HtmlView.php

namespace ThApi\Component\ThApi\Administrator\View\Configuration;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;

    public function display($tpl = null)
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

        // Sprawdź błędy - POPRAWIONE: sprawdź czy errors jest array
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors) > 0) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        $this->prepareDocument();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        ToolbarHelper::title(Text::_('COM_THAPI_CONFIGURATION'), 'cog');
        
        ToolbarHelper::apply('configuration.apply');
        ToolbarHelper::save('configuration.save');
        ToolbarHelper::cancel('configuration.cancel', 'JTOOLBAR_CLOSE');
    }

    protected function prepareDocument()
    {
        // Załaduj wymagane JavaScripty Joomla
        HTMLHelper::_('behavior.formvalidator');
        HTMLHelper::_('behavior.keepalive');
        
        // Załaduj jQuery
        HTMLHelper::_('jquery.framework');
        
        // Dodaj skrypt do poprawnego działania toolbar
        $doc = Factory::getDocument();
        $doc->addScriptDeclaration("
            Joomla.submitbutton = function(task) {
                if (task == 'configuration.cancel' || document.formvalidator.isValid(document.getElementById('thapi-config-form'))) {
                    Joomla.submitform(task, document.getElementById('thapi-config-form'));
                }
            }
        ");
    }
}
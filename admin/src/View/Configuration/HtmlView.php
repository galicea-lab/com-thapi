<?php
// admin/src/View/Configuration/HtmlView.php

namespace ThApi\Component\ThApi\Administrator\View\Configuration;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Configuration view.
 *
 * The configuration form is the default screen of the component,
 * therefore the "edit" layout is used when no layout is requested.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  \Joomla\CMS\Form\Form|false
     */
    protected $form;

    /**
     * @var  object|null
     */
    protected $item;

    public function display($tpl = null)
    {
        // Without an explicit layout, render the form ("edit") instead of falling back to "default".
        if (!$this->getLayout() || $this->getLayout() === 'default') {
            $this->setLayout('edit');
        }

        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

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
        ToolbarHelper::title(Text::_('COM_THAPI_CONFIGURATION'), 'cog');

        ToolbarHelper::apply('configuration.apply');
        ToolbarHelper::save('configuration.save');
        ToolbarHelper::cancel('configuration.cancel', 'JTOOLBAR_CLOSE');

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_thapi')) {
            ToolbarHelper::preferences('com_thapi');
        }
    }

    protected function prepareDocument()
    {
        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->useScript('form.validate')
            ->useScript('keepalive');

        // The form uses a custom id, so make sure the toolbar buttons validate the right form.
        $this->getDocument()->addScriptDeclaration(<<<'JS'
            Joomla.submitbutton = function (task) {
                var form = document.getElementById('thapi-config-form');

                if (!form) {
                    return;
                }

                if (task && task !== 'configuration.cancel' && document.formvalidator && !document.formvalidator.isValid(form)) {
                    return;
                }

                Joomla.submitform(task, form);
            };
        JS);
    }
}

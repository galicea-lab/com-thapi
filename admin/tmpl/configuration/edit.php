<?php
// admin/tmpl/configuration/edit.php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \ThApi\Component\ThApi\Administrator\View\Configuration\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_thapi&view=configuration&layout=edit'); ?>"
      method="post"
      name="adminForm"
      id="thapi-config-form"
      class="form-validate main-card">

    <?php if (empty($this->form)) : ?>
        <div class="alert alert-danger">
            <h2 class="alert-heading"><?php echo Text::_('COM_THAPI_CONFIG_FORM_ERROR'); ?></h2>
            <p><?php echo Text::_('COM_THAPI_CONFIG_FORM_ERROR_DESC'); ?></p>
            <ul>
                <li>administrator/components/com_thapi/forms/configuration.xml</li>
            </ul>
        </div>
    <?php else : ?>
        <div class="row">
            <div class="col-lg-9 col-xl-8">
                <?php echo HTMLHelper::_('uitab.startTabSet', 'thapiConfigTabs', ['active' => 'basic', 'recall' => true, 'breakpoint' => 768]); ?>

                <?php foreach ($this->form->getFieldsets() as $name => $fieldset) : ?>
                    <?php
                    // Pomiń puste fieldsety (np. ukryte lub niezdefiniowane w XML).
                    if ($name === '' || !$this->form->getFieldset($name)) {
                        continue;
                    }

                    $label = empty($fieldset->label)
                        ? 'COM_THAPI_CONFIG_' . strtoupper($name)
                        : $fieldset->label;
                    $escName = $this->escape($name);
                    ?>
                    <?php echo HTMLHelper::_('uitab.addTab', 'thapiConfigTabs', $escName, Text::_($label)); ?>
                        <fieldset id="fieldset-<?php echo $escName; ?>" class="options-form">
                            <legend><?php echo Text::_($label); ?></legend>

                            <?php if (!empty($fieldset->description)) : ?>
                                <div class="tab-description alert alert-info">
                                    <span class="icon-info-circle" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                                    <?php echo Text::_($fieldset->description); ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-grid">
                                <?php echo $this->form->renderFieldset($name); ?>
                            </div>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php endforeach; ?>

                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
            </div>
        </div>
    <?php endif; ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

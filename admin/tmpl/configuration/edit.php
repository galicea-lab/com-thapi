<?php
// admin/tmpl/configuration/edit.php
?>
<?php defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$app = Factory::getApplication();

/* * UWAGA: Sekcje debugujące zostały usunięte. 
 * Formularz jest wczytywany (wiemy to, bo zmiana pól działała).
 */
?>

<form action="<?php echo Route::_('index.php?option=com_thapi&view=configuration&layout=edit'); ?>" method="post" name="adminForm" id="thapi-config-form" class="form-horizontal">
    
    <?php if (isset($this->form) && $this->form): ?>
        
        <div class="row-fluid">
            <div class="span12">
                <h3>Basic Settings</h3>
                <?php echo $this->form->renderFieldset('basic'); ?>
            </div>
        </div>
        
        <div class="row-fluid">
            <div class="span12">
                <h3>Export Settings</h3>
                <?php echo $this->form->renderFieldset('export'); ?>
            </div>
        </div>

        <div class="row-fluid">
            <div class="span12">
                <h3>Treści i Slider (Content Settings)</h3> 
                <?php echo $this->form->renderFieldset('content_settings'); ?>
            </div>
        </div>
        
    <?php else: ?>
        <div class="alert alert-error">
            <h4>Form Error</h4>
            <p>Cannot load configuration form. Please check:</p>
            <ul>
                <li>File: administrator/components/com_thapi/forms/configuration.xml exists</li>
                <li>File has correct XML syntax</li>
                <li>Check Joomla error logs for details</li>
            </ul>
        </div>
    <?php endif; ?>
    
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="id" value="<?php echo isset($this->item->id) ? $this->item->id : 0; ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php
// admin/tmpl/configuration/default.php
?>
<?php defined('_JEXEC') or die;

// Po prostu przekieruj do edycji
$app = Joomla\CMS\Factory::getApplication();
$app->redirect(Joomla\CMS\Router\Route::_('index.php?option=com_thapi&view=configuration&layout=edit', false));
?>
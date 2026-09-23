<?php
// admin/src/Extension/ThApiComponent.php
namespace ThApi\Component\ThApi\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

defined('_JEXEC') or die;

class ThApiComponent extends MVCComponent implements BootableExtensionInterface
{
	public function boot(ContainerInterface $container): void
	{
		// Nic tu nie robimy – nie ma HTML services, tagów, etc.
	}
}
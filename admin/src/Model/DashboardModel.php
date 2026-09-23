<?php
// admin/src/Model/DashboardModel.php

namespace ThApi\Component\ThApi\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;

class DashboardModel extends BaseDatabaseModel
{
    public function getStats()
    {
        $db = $this->getDatabase();
        
        // Podstawowe statystyki
        $stats = [
            'total_items' => $this->getTotalItems(),
            'active_items' => $this->getActiveItems(),
            'last_updated' => $this->getLastUpdated(),
            'system_info' => $this->getSystemInfo()
        ];

        return $stats;
    }

    protected function getTotalItems()
    {
        $db = $this->getDatabase();
        
        // Sprawdź czy tabela istnieje
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'thapi_items';
        
        if (!in_array($tableName, $tables)) {
            return 0;
        }
        
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__thapi_items');
        
        try {
            return $db->setQuery($query)->loadResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getActiveItems()
    {
        $db = $this->getDatabase();
        
        // Sprawdź czy tabela istnieje
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'thapi_items';
        
        if (!in_array($tableName, $tables)) {
            return 0;
        }
        
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__thapi_items')
            ->where('state = 1');
        
        try {
            return $db->setQuery($query)->loadResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getLastUpdated()
    {
        $db = $this->getDatabase();
        
        // Sprawdź czy tabela istnieje
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'thapi_items';
        
        if (!in_array($tableName, $tables)) {
            return 'Never';
        }
        
        $query = $db->getQuery(true)
            ->select('MAX(created)')
            ->from('#__thapi_items');
        
        try {
            $result = $db->setQuery($query)->loadResult();
            return $result ? $result : 'Never';
        } catch (\Exception $e) {
            return 'Never';
        }
    }

    protected function getSystemInfo()
    {
        return [
            'joomla_version' => JVERSION,
            'php_version' => PHP_VERSION,
            'database_version' => $this->getDatabase()->getVersion()
        ];
    }
}
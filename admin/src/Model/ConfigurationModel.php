<?php
// admin/src/Model/ConfigurationModel.php

namespace ThApi\Component\ThApi\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;

class ConfigurationModel extends FormModel
{
    protected $configData;

    public function getForm($data = [], $loadData = true)
    {
        try {
            // WAŻNE: Weryfikacja ścieżki jest poprawna, choć można by użyć $formPath w loadForm.
            $formPath = JPATH_ADMINISTRATOR . '/components/com_thapi/forms/configuration.xml';
            
            if (!file_exists($formPath)) {
                Factory::getApplication()->enqueueMessage('Form file not found: ' . $formPath, 'error');
                return false;
            }

            // Używamy nazwy pliku 'configuration', ale jeśli masz dalej problem,
            // użyj $formPath jako drugiego argumentu (jak w poprzedniej sugestii), 
            // by wymusić wczytanie: $this->loadForm('com_thapi.configuration', $formPath, ...)
            $form = $this->loadForm('com_thapi.configuration', 'configuration', 
                ['control' => 'jform', 'load_data' => $loadData]);

            if (empty($form)) {
                Factory::getApplication()->enqueueMessage('Cannot load form com_thapi.configuration', 'error');
                return false;
            }

            return $form;

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Form loading error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_thapi.config.data', []);
        
        if (empty($data)) {
            $data = $this->getConfig();
        }

        // Konwertuj obiekt na array jeśli potrzeba
        if (is_object($data)) {
            $data = (array) $data;
        }

        return $data;
    }

    public function getConfig()
    {
        if ($this->configData === null) {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'thapi_config';
            
            if (in_array($tableName, $tables)) {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__thapi_config')
                    ->where('id = 1');
                
                try {
                    $this->configData = $db->setQuery($query)->loadObject();
                    
                    // NOWE: DEKODOWANIE JSON DLA SLIDERA (aby formularz widział to jako tablicę)
                    if ($this->configData && !empty($this->configData->slider_images)) {
                        $this->configData->slider_images = json_decode($this->configData->slider_images, true);
                    }
                    
                } catch (\Exception $e) {
                    $this->configData = $this->getDefaultConfig();
                }
            } else {
                $this->configData = $this->getDefaultConfig();
            }
        }

        return $this->configData;
    }

    protected function getDefaultConfig()
    {
        $config = new \stdClass();
        $config->id = 1;
        $config->api_key = '';
        $config->api_secret = '';
        $config->enable_api = 1;
        $config->items_per_page = 20;
        $config->export_categories = '';
        $config->default_source_id = 1;
        $config->enable_json_export = 1;
        
        // NOWE POLA DOMYŚLNE
        $config->featured_article_catid = 0;
        $config->slider_images = []; 
        
        return $config;
    }

    public function save($data) {
        $db = $this->getDatabase();
        
        try {
            // Upewnienie się, że tabela zawiera nowe kolumny
            $this->createConfigTableIfNotExists();
            
            if (!isset($data['id']) || empty($data['id'])) {
                $data['id'] = 1;
            }

            // === NAPRAWA: Konwersja pustych wartości na odpowiednie typy ===
            // Dla featured_article_catid - jeśli puste lub null, ustaw 0
            if (!isset($data['featured_article_catid']) || $data['featured_article_catid'] === '' || $data['featured_article_catid'] === null) {
                $data['featured_article_catid'] = 0;
            } else {
                $data['featured_article_catid'] = (int) $data['featured_article_catid'];
            }

            // Dla innych pól numerycznych
            if (!isset($data['items_per_page']) || $data['items_per_page'] === '') {
                $data['items_per_page'] = 20;
            } else {
                $data['items_per_page'] = (int) $data['items_per_page'];
            }

            if (!isset($data['default_source_id']) || $data['default_source_id'] === '') {
                $data['default_source_id'] = 1;
            } else {
                $data['default_source_id'] = (int) $data['default_source_id'];
            }

            // Dla pól boolean (radio) - upewnij się że to INT
            $data['enable_api'] = isset($data['enable_api']) ? (int) $data['enable_api'] : 1;
            $data['enable_json_export'] = isset($data['enable_json_export']) ? (int) $data['enable_json_export'] : 1;

            // Konwersja slider_images z subform na JSON
            if (isset($data['slider_images']) && is_array($data['slider_images'])) {
                // Usuń puste slajdy (opcjonalnie)
                $filteredSlides = array_filter($data['slider_images'], function($slide) {
                    // Zachowaj slajd jeśli ma obrazek lub tytuł
                    return !empty($slide['image']) || !empty($slide['title']);
                });
                
                // Zresetuj indeksy
                $data['slider_images'] = json_encode(array_values($filteredSlides));
            } else {
                $data['slider_images'] = '[]';
            }

            // Konwertuj array na object
            $configObject = (object) $data;
            
            // Sprawdź czy rekord już istnieje i wykonaj update lub insert
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__thapi_config')
                ->where('id = ' . (int)$data['id']);
            $exists = $db->setQuery($query)->loadResult();
            
            if ($exists) {
                $result = $db->updateObject('#__thapi_config', $configObject, 'id');
            } else {
                $result = $db->insertObject('#__thapi_config', $configObject);
            }
            
            if ($result) {
                $this->cleanCache('com_thapi');
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            Factory::getApplication()->enqueueMessage('Save error: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    public function save_bak($data)
    {
        $db = $this->getDatabase();
        
        try {
            // Upewnienie się, że tabela zawiera nowe kolumny
            $this->createConfigTableIfNotExists();
            
            if (!isset($data['id']) || empty($data['id'])) {
                $data['id'] = 1;
            }

            // WAŻNE: Konwersja tablicy subform na JSON przed zapisem
            if (isset($data['slider_images']) && is_array($data['slider_images'])) {
                $data['slider_images'] = json_encode(array_values($data['slider_images']));
            } else {
                $data['slider_images'] = ''; 
            }
            // Konwertuj array na object
            $configObject = (object) $data;
            
            // Sprawdź czy rekord już istnieje i wykonaj update lub insert
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__thapi_config')
                ->where('id = ' . (int)$data['id']);
            $exists = $db->setQuery($query)->loadResult();
            
            if ($exists) {
                $result = $db->updateObject('#__thapi_config', $configObject, 'id');
            } else {
                $result = $db->insertObject('#__thapi_config', $configObject);
            }
            
            if ($result) {
                $this->cleanCache('com_thapi');
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            Factory::getApplication()->enqueueMessage('Save error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    protected function createConfigTableIfNotExists()
    {
        $db = $this->getDatabase();
        $tableName = '#__thapi_config';
        
        $tables = $db->getTableList();
        $fullTableName = str_replace('#__', $db->getPrefix(), $tableName);
        
        if (!in_array($fullTableName, $tables)) {
            // Pełny schemat z nowymi kolumnami dla nowego tworzenia tabeli
            $query = "CREATE TABLE IF NOT EXISTS `{$fullTableName}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `api_key` varchar(255) NOT NULL DEFAULT '',
                `api_secret` varchar(255) NOT NULL DEFAULT '',
                `enable_api` tinyint(1) NOT NULL DEFAULT '1',
                `items_per_page` int(11) NOT NULL DEFAULT '20',
                `export_categories` varchar(255) NOT NULL DEFAULT '',
                `default_source_id` int(11) NOT NULL DEFAULT '1',
                `enable_json_export` tinyint(1) NOT NULL DEFAULT '1',
                `featured_article_catid` int(11) NOT NULL DEFAULT '0',
                `slider_images` mediumtext, 
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;";
            
            $db->setQuery($query)->execute();
            
            $defaultConfig = $this->getDefaultConfig();
            if (is_array($defaultConfig->slider_images)) {
                $defaultConfig->slider_images = json_encode($defaultConfig->slider_images);
            }
            $db->insertObject($tableName, $defaultConfig);
        } else {
            // Migracja: Dodanie brakujących kolumn, jeśli tabela już istnieje
            $columns = $db->getTableColumns($tableName);
            
            if (!isset($columns['featured_article_catid'])) {
                $db->setQuery("ALTER TABLE `{$fullTableName}` ADD COLUMN `featured_article_catid` int(11) NOT NULL DEFAULT '0'")->execute();
            }
            if (!isset($columns['slider_images'])) {
                $db->setQuery("ALTER TABLE `{$fullTableName}` ADD COLUMN `slider_images` mediumtext")->execute();
            }
        }
    }

    protected function cleanCache($group = null, $client_id = 0)
    {
        parent::cleanCache($group ?? 'com_thapi');
    }
}
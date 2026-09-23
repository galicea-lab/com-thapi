<?php
// src/Controller/ApiController.php
namespace ThApi\Component\ThApi\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;
// === CORS FIX – pod defined('_JEXEC') ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(200);
    exit();
}
// =======================================================

class ApiController extends BaseController
{
/*    protected $publicTasks = [
        'exportJson',
        'checkUpdates',
        'getCategories'
    ];
*/
    public function exportJson()
    {
        $this->sendJsonResponse();

        try {
            $model = $this->getModel('Export', 'Site');
            
            if (!$model) {
                throw new \Exception('Model Export not found');
            }

            $input = $this->app->input;
            $categories = $input->get('categories', '', 'STRING');
            $categoryIds = $categories ? array_filter(array_map('intval', explode(',', $categories))) : null;

            $data = $model->getExportData($categoryIds);

            if (empty($data)) {
                http_response_code(404);
                echo json_encode([
                    'error' => true,
                    'message' => 'No data found'
                ], JSON_PRETTY_PRINT);
            } else {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }

        jexit();
    }

    public function checkUpdates()
    {
        $this->sendJsonResponse();

        try {
            $input = $this->app->input;
            $since = $input->get('since', date('Y-m-d H:i:s', strtotime('-1 day')), 'STRING');
            
            // Bezpośrednie zapytanie do bazy zamiast używać modelu
            $db = Factory::getDbo();
            
            // Sprawdź nowe i zaktualizowane artykuły od podanej daty
            $query = $db->getQuery(true)
                ->select([
                    'COUNT(*) as new_articles',
                    'MAX(created) as last_created',
                    'MAX(modified) as last_modified'
                ])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('created') . ' > ' . $db->quote($since));
            
            $db->setQuery($query);
            $newData = $db->loadAssoc();
            
            $query = $db->getQuery(true)
                ->select('COUNT(*) as updated_articles')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('modified') . ' > ' . $db->quote($since))
                ->where($db->quoteName('modified') . ' != ' . $db->quoteName('created'));
            
            $db->setQuery($query);
            $updatedData = $db->loadAssoc();

            $data = [
                'lastUpdate' => date('Y-m-d H:i:s'),
                'since' => $since,
                'newArticles' => (int)$newData['new_articles'],
                'updatedArticles' => (int)$updatedData['updated_articles'],
                'lastCreated' => $newData['last_created'],
                'lastModified' => $newData['last_modified']
            ];

            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }

        jexit();
    }

    public function getCategories()
    {
        $this->sendJsonResponse();

        try {
            $input = $this->app->input;
            $withArticles = $input->get('withArticles', 0, 'INT');
            
            // Bezpośrednie zapytanie do bazy

            $db = Factory::getDbo();
            
            $query = $db->getQuery(true)
                ->select([
                    'c.id',
                    'c.title AS name',
                    'c.description',
                    'c.alias',
                    'c.note',
                    'c.params',
                    '(SELECT COUNT(*) FROM ' . $db->quoteName('#__content') . ' WHERE catid = c.id AND state = 1) as article_count'
                ])
                ->from($db->quoteName('#__categories', 'c'))
                ->where($db->quoteName('c.published') . ' = 1')
                ->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_content'))
                ->order('c.lft ASC');

            $db->setQuery($query);
            $categories = $db->loadAssocList();

            // Jeśli żądano artykułów, dodaj je do każdej kategorii
            if ($withArticles) {
                foreach ($categories as &$category) {
                    $category['articles'] = $this->getCategoryArticles($category['id']);
                }
            }

            echo json_encode([
                'categories' => $categories
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }

        jexit();
    }


    public function categoryArticles()
    {
        $this->sendJsonResponse();

        try {
            $input = $this->app->input;
            $categoryId = $input->get('categoryId', 0, 'INT');
            $limit = $input->get('limit', 10, 'INT');
            $articles = $this->getCategoryArticles($categoryId,false,$limit);

            echo json_encode([
                'articles' => $articles
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }

        jexit();
    }

    private function getCategoryArticles($categoryId, $full = 0, $limit = 10)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        // 1. Podzapytanie: Pobierz ID kategorii głównej i wszystkich jej dzieci
        $subQuery = $db->getQuery(true)
            ->select('sub.id')
            ->from($db->quoteName('#__categories', 'sub'))
            ->join('INNER', $db->quoteName('#__categories', 'parent') . ' ON sub.lft >= parent.lft AND sub.rgt <= parent.rgt')
            ->where('parent.id = ' . (int)$categoryId);

        // 2. Budowa głównego zapytania
        $columns = [
            'a.id', 'a.title', 'a.alias', 'a.introtext',
            'a.created', 'a.modified',
            'COALESCE(a.created_by_alias, u.name) AS author'
        ];

        if ($full) {
            $columns[] = 'a.fulltext';
        }

        $query->select($columns)
            ->from($db->quoteName('#__content', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
            ->where($db->quoteName('a.state') . ' = 1')
            // Zmieniamy operator "=" na "IN" z podzapytaniem
            ->where($db->quoteName('a.catid') . ' IN (' . $subQuery . ')')
            ->order('a.created DESC')
            ->setLimit($limit);

        $db->setQuery($query);
        $articles = $db->loadAssocList();

        foreach ($articles as &$article) {
            $article['introtext'] = strip_tags($article['introtext']);
        }

        return $articles;
    }

    private function getCategoryArticles1($categoryId,$full=1,$limit=10)
    {
        $db = Factory::getDbo();
        if ($fill) 
         $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.introtext',
                'a.fulltext',
                'a.created',
                'a.modified',
                'COALESCE(a.created_by_alias, u.name) AS author'
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.catid') . ' = ' . (int)$categoryId)
            ->order('a.created DESC')
            ->setLimit($limit); // Ostatnie ... artykułów
       else
         $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.title',
                'a.alias',
                'a.introtext',
                'a.created',
                'a.modified',
                'COALESCE(a.created_by_alias, u.name) AS author'
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.catid') . ' = ' . (int)$categoryId)
            ->order('a.created DESC')
            ->setLimit($limit); // Ostatnie ... artykułów

        $db->setQuery($query);
        $articles = $db->loadAssocList();

        // Oczyść introtext z HTML
        foreach ($articles as &$article) {
            $article['introtext'] = strip_tags($article['introtext']);
        }

        return $articles;
    }


    public function getMainMenu()
    {
        $this->sendJsonResponse();
        
        try {
            $db = Factory::getDbo();
            // Zapytanie o elementy menu (np. dla 'mainmenu')
            $menu_type = 'mainmenu'; // Zmień na menutype, który Cię interesuje
            
            $query_menu = $db->getQuery(true)
                ->select([
                    'm.id',
                    'm.title AS menu_title',
                    'm.alias',
                    'm.menutype',
                    'm.path',
                    'm.link',
                    'm.type', // np. 'component', 'url'
                    'm.template_style_id'
                ])
                ->from($db->quoteName('#__menu', 'm'))
                ->where($db->quoteName('m.published') . ' = 1')
                ->where($db->quoteName('m.client_id') . ' = 0') // 0 dla frontend
                ->where($db->quoteName('m.menutype') . ' = ' . $db->quote($menu_type))
                ->order('m.lft ASC');
            
            $db->setQuery($query_menu);
            $menu_items = $db->loadAssocList();

            // *** TUTAJ JEST WYŚWIETLENIE DANYCH JSON, KTÓREGO BRAKOWAŁO ***
            echo json_encode([
                'menu_type' => $menu_type,
                'menu_items' => $menu_items
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }

        jexit();
    }

    private function sendJsonResponse()
    {
      // Wyczyść ewentualne bufory wyjścia
      while (ob_get_level()) {
        ob_end_clean();
      }

      // Nagłówki CORS – najważniejsze linie!
      header('Access-Control-Allow-Origin: *');                    // w produkcji zmień na konkretną domenę
      header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
      header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
      header('Access-Control-Allow-Credentials: false');            // true tylko jeśli używasz ciasteczek

      // Content-Type JSON
      header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Zwraca listę obrazków do slidera z konfiguracji komponentu
     */
    public function getSlider()
    {
        $this->sendJsonResponse();
        try {
            $config = $this->getConfig();
            
            // Pobierz dane z JSON
            $sliderData = !empty($config->slider_images) ? json_decode($config->slider_images, true) : [];
            
            // Przetwórz ścieżki do obrazków na pełne adresy URL
            $root = Uri::root();
            foreach ($sliderData as &$slide) {
                if (!empty($slide['image'])) {
                    // Jeśli obrazek nie ma http na początku, dodaj base url
                    if (strpos($slide['image'], 'http') !== 0) {
                        $slide['image'] = $root . $slide['image'];
                    } else {
                        //$slide['image_url'] = $slide['image'];
                    }
                }
            }

            echo json_encode(['slider' => $sliderData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
        jexit();
    }

    /**
     * Zwraca listę kategorii Phoca Gallery
     */
    public function getPhocaCategories()
    {
        $this->sendJsonResponse();
        try {
            $db = Factory::getDbo();
            
            // Sprawdź czy tabela istnieje (aby uniknąć błędu 500 jeśli Phoca nie jest zainstalowana)
            $tables = $db->getTableList();
            $phocaTable = $db->getPrefix() . 'phocagallery_categories';
            
            if (!in_array($phocaTable, $tables)) {
                throw new \Exception('Phoca Gallery not installed');
            }

            $query = $db->getQuery(true)
                ->select([
                    'id', 'title', 'alias', 'image', 'description', 
                    'count as num_images' // Phoca często trzyma liczbę zdjęć w kolumnie 'count' lub oblicza dynamicznie
                ])
                ->from($db->quoteName('#__phocagallery_categories'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('approved') . ' = 1')
                ->order('ordering ASC');
            
            $db->setQuery($query);
            $categories = $db->loadAssocList();
            
            // Dodaj pełne ścieżki do miniaturek kategorii
            $root = Uri::root();
            foreach ($categories as &$cat) {
                 // Dostosuj ścieżkę zależnie od struktury folderów Phoca
                if (!empty($cat['image'])) {
                    $cat['image_url'] = $root . 'images/phocagallery/' . $cat['image']; 
                } else {
                    $cat['image_url'] = null;
                }
            }

            echo json_encode(['phoca_categories' => $categories], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
        jexit();
    }

    /**
     * Zwraca jeden najnowszy artykuł z kategorii ustawionej w konfiguracji
     */
    public function getFeaturedArticle()
    {
        $this->sendJsonResponse();
        try {
            $config = $this->getConfig();
            $catId = (int) ($config->featured_article_catid ?? 0);

            if ($catId === 0) {
                throw new \Exception('Featured article category not configured');
            }

            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select([
                    'a.id', 'a.title', 'a.alias', 'a.introtext', 
                    'a.fulltext', 'a.created', 
                    'c.title as category_title',
                    'u.name as author_name'
                ])
                ->from($db->quoteName('#__content', 'a'))
                ->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
                ->where($db->quoteName('a.state') . ' = 1')
//                ->where($db->quoteName('a.catid') . ' = ' . $catId)
                // Opcjonalnie: sprawdź daty publikacji
//                ->where('(a.publish_up IS NULL OR a.publish_up <= NOW())')
//                ->where('(a.publish_down IS NULL OR a.publish_down >= NOW())')
                ->order('a.created DESC')
                ->setLimit(1);

            $db->setQuery($query);
            $article = $db->loadAssoc();

            if ($article) {
                // Wyciągnij obrazek z introtext lub images (zależnie jak używasz Joomla)
                $images = json_decode($article['images'] ?? '{}');
                $article['image_intro'] = $images->image_intro ?? null;
                $article['image_fulltext'] = $images->image_fulltext ?? null;
                
                // Opcjonalnie wyczyść tagi HTML z introtextu
                // $article['introtext'] = strip_tags($article['introtext']);
            }

//            echo json_encode(['featured_article' => $article], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
        jexit();
    }

    // Pomocnicza metoda do pobierania konfiguracji
    private function getConfig()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__thapi_config')
            ->where('id = 1');
        
        $config = $db->setQuery($query)->loadObject();
        
        if (!$config) {
            // Fallback na domyślne, jeśli baza pusta
            return (object) [
                'slider_images' => '[]',
                'featured_article_catid' => 0
            ];
        }
        return $config;
    }

    private function sendError($code, $message)
    {
        http_response_code($code);
        echo json_encode([
            'error' => true,
            'message' => $message
        ], JSON_PRETTY_PRINT);
    }
    
 

}
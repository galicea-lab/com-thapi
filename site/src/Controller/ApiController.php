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


    /**
     * Najnowsze opublikowane artykuły – zajawka CMS na stronie głównej.
     *
     * Parametry:
     *   limit           – liczba artykułów (1–24, domyślnie 5)
     *   offset          – przesunięcie
     *   catid           – jedno ID kategorii
     *   categories      – lista ID kategorii rozdzielona przecinkami
     *   includeChildren – 1 = uwzględnij podkategorie
     *   featured        – 1/0 (tylko wyróżnione / tylko zwykłe)
     *   exclude         – lista ID artykułów do pominięcia
     */
    public function getLatestArticles()
    {
        $this->sendJsonResponse();

        try {
            $input  = $this->app->input;
            $limit  = max(1, min(24, (int) $input->get('limit', 5, 'INT')));
            $offset = max(0, (int) $input->get('offset', 0, 'INT'));

            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            $query = $db->getQuery(true)
                ->select([
                    'a.id', 'a.title', 'a.alias', 'a.catid',
                    'a.introtext', 'a.fulltext', 'a.images', 'a.created', 'a.modified',
                    'a.publish_up', 'a.hits', 'a.featured', 'a.created_by_alias',
                    'c.title AS category_title', 'c.alias AS category_alias',
                    'u.name AS author_name',
                ])
                ->from($db->quoteName('#__content', 'a'))
                ->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
                ->where($db->quoteName('a.state') . ' = 1')
                ->where($db->quoteName('a.access') . ' = 1')
                ->where($db->quoteName('c.published') . ' = 1')
                ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' <= ' . $db->quote($now) . ')')
                ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' = ' . $db->quote('0000-00-00 00:00:00') . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $db->quote($now) . ')')
                ->order('a.created DESC, a.id DESC')
                ->setLimit($limit, $offset);

            // Filtrowanie po kategoriach (z opcjonalnymi podkategoriami).
            $catIds = array_filter(array_map('intval', explode(',', (string) $input->get('categories', '', 'STRING'))));
            $singleCat = (int) $input->get('catid', 0, 'INT');
            if ($singleCat > 0) {
                $catIds[] = $singleCat;
            }
            $catIds = array_values(array_unique($catIds));

            if (!empty($catIds)) {
                if ((int) $input->get('includeChildren', 0, 'INT') === 1) {
                    $subQuery = $db->getQuery(true)
                        ->select('sub.id')
                        ->from($db->quoteName('#__categories', 'sub'))
                        ->join('INNER', $db->quoteName('#__categories', 'parent') . ' ON sub.lft >= parent.lft AND sub.rgt <= parent.rgt')
                        ->where('parent.id IN (' . implode(',', $catIds) . ')');
                    $query->where($db->quoteName('a.catid') . ' IN (' . $subQuery . ')');
                } else {
                    $query->where($db->quoteName('a.catid') . ' IN (' . implode(',', $catIds) . ')');
                }
            }

            $excludeIds = array_filter(array_map('intval', explode(',', (string) $input->get('exclude', '', 'STRING'))));
            if (!empty($excludeIds)) {
                $query->where($db->quoteName('a.id') . ' NOT IN (' . implode(',', $excludeIds) . ')');
            }

            $featured = $input->get('featured', null, 'STRING');
            if ($featured === '0' || $featured === '1') {
                $query->where($db->quoteName('a.featured') . ' = ' . (int) $featured);
            }

            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];
            $root = Uri::root();
            $articles = [];

            foreach ($rows as $row) {
                $images = [];
                if (!empty($row['images'])) {
                    $decoded = json_decode($row['images'], true);
                    if (is_array($decoded)) {
                        $images = $decoded;
                    }
                }

                $intro = $this->plainText($row['introtext'] ?? '');
                if ($intro === '') {
                    // Część artykułów Joomla ma treść wyłącznie w polu fulltext.
                    $intro = $this->plainText($row['fulltext'] ?? '');
                }

                $author  = trim((string) ($row['created_by_alias'] ?: $row['author_name']));
                $catId   = (int) $row['catid'];
                $catName = trim((string) ($row['category_title'] ?? ''));

                $articles[] = [
                    'id'              => (int) $row['id'],
                    'title'           => $row['title'],
                    'alias'           => $row['alias'],
                    'catid'           => $catId,
                    'category_title'  => $catName !== '' ? $catName : null,
                    'category_alias'  => $row['category_alias'] ?? null,
                    'introtext'       => $intro,
                    'excerpt'         => $this->excerptText($intro, 240),
                    'image_intro'     => $this->resolveMediaUrl($images['image_intro'] ?? '', $root),
                    'image_intro_alt' => trim((string) ($images['image_intro_alt'] ?? '')),
                    'image_fulltext'  => $this->resolveMediaUrl($images['image_fulltext'] ?? '', $root),
                    'created'         => $row['created'],
                    'modified'        => $row['modified'] ?: null,
                    'publish_up'      => $row['publish_up'] ?: null,
                    'author'          => $author !== '' ? $author : null,
                    'hits'            => (int) $row['hits'],
                    'featured'        => (int) $row['featured'],
                    'url'             => $this->routeUrl('index.php?option=com_content&view=article&id=' . (int) $row['id'] . '&catid=' . $catId),
                    'category_url'    => $catName !== '' ? $this->routeUrl('index.php?option=com_content&view=category&id=' . $catId) : null,
                ];
            }

            echo json_encode([
                'articles' => $articles,
                'count'    => count($articles),
                'limit'    => $limit,
                'offset'   => $offset,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
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


    /**
     * Elementy menu głównego (domyślnie menutype = mainmenu).
     * Obsługuje także warianty "main-menu" / "main_menu" i pokrewne.
     * Parametry: menutype, limit, topLevel.
     */
    public function getMainMenu()
    {
        $this->sendJsonResponse();

        try {
            $input     = $this->app->input;
            $requested = trim((string) $input->get('menutype', 'mainmenu', 'STRING')) ?: 'mainmenu';
            $limit     = max(1, min(100, (int) $input->get('limit', 50, 'INT')));
            $topLevel  = (int) $input->get('topLevel', 0, 'INT') === 1;
            $menutype  = $this->resolveMenuType($requested, [
                'mainmenu',
                'main-menu',
                'main_menu',
                'menu',
                'mnu_mainmenu',
                'mnu-mainmenu',
            ]);
            $items     = $this->loadMenuItems($menutype, $limit, $topLevel);

            echo json_encode([
                'menu_type'           => $menutype,
                'requested_menu_type' => $requested,
                'menu_items'          => $items,
                'count'               => count($items),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }

        jexit();
    }

    /**
     * Elementy dowolnego menu (np. mnu-polecamy) wraz z gotowymi adresami URL.
     * Parametry: menutype, limit, topLevel.
     */
    public function getMenuItems()
    {
        $this->sendJsonResponse();

        try {
            $input    = $this->app->input;
            $menutype = trim((string) $input->get('menutype', 'mainmenu', 'STRING')) ?: 'mainmenu';
            $limit    = max(1, min(100, (int) $input->get('limit', 20, 'INT')));
            $topLevel = (int) $input->get('topLevel', 0, 'INT') === 1;
            $items    = $this->loadMenuItems($menutype, $limit, $topLevel);

            echo json_encode([
                'menu_type'  => $menutype,
                'menu_items' => $items,
                'count'      => count($items),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }

        jexit();
    }

    /**
     * Linki polecanych stron – domyślnie z menu o menutype "mnu-polecamy".
     *
     * W instalacjach Joomla spotyka się zarówno "mnu-polecamy", jak i
     * "mnu_polecamy" – dlatego dla znanych aliasów sprawdzamy oba warianty.
     * Zwracane są wyłącznie pozycje z poprawnym adresem URL.
     */
    public function getRecommendedLinks()
    {
        $this->sendJsonResponse();

        try {
            $input     = $this->app->input;
            $requested = trim((string) $input->get('menutype', 'mnu-polecamy', 'STRING')) ?: 'mnu-polecamy';
            $limit     = max(1, min(50, (int) $input->get('limit', 10, 'INT')));

            $menutype = $requested;
            $items    = [];

            foreach ($this->getRecommendedMenuTypeCandidates($requested) as $candidate) {
                $candidateItems = array_values(array_filter(
                    $this->loadMenuItems($candidate, $limit, false),
                    static function (array $item) {
                        return !empty($item['url']);
                    }
                ));

                if (!empty($candidateItems)) {
                    $menutype = $candidate;
                    $items    = $candidateItems;
                    break;
                }
            }

            echo json_encode([
                'menu_type'           => $menutype,
                'requested_menu_type' => $requested,
                'menu_items'          => $items,
                'count'               => count($items),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }

        jexit();
    }

    /**
     * Lista menutype'ów wraz z liczbą pozycji – pomocniczo przy diagnozie menu.
     */
    public function getMenuTypes()
    {
        $this->sendJsonResponse();

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select([
                    'm.menutype',
                    'COUNT(*) AS total',
                    'SUM(CASE WHEN m.published = 1 THEN 1 ELSE 0 END) AS published',
                    'SUM(CASE WHEN m.client_id = 0 THEN 1 ELSE 0 END) AS site',
                ])
                ->from($db->quoteName('#__menu', 'm'))
                ->group($db->quoteName('m.menutype'))
                ->order($db->quoteName('m.menutype') . ' ASC');

            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];

            foreach ($rows as &$row) {
                $row['total']     = (int) $row['total'];
                $row['published'] = (int) $row['published'];
                $row['site']      = (int) $row['site'];
            }
            unset($row);

            echo json_encode([
                'menu_types' => $rows,
                'count'      => count($rows),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }

        jexit();
    }

    /**
     * Warianty menutype dla ramki "Polecamy" (myślnik / podkreślenie / aliasy).
     */
    private function getRecommendedMenuTypeCandidates($menutype)
    {
        $menutype   = trim((string) $menutype);
        $normalized = strtolower($menutype);
        $candidates = [$menutype];

        $aliases = [
            'mnu-polecamy',
            'mnu_polecamy',
            'polecamy',
            'polecane',
            'mnu-polecane',
            'mnu_polecane',
            'recommended',
            'recommended-links',
        ];

        $isRecommended = in_array($normalized, $aliases, true)
            || strpos($normalized, 'polecamy') !== false
            || strpos($normalized, 'polecane') !== false;

        if ($isRecommended) {
            $candidates[] = str_replace('_', '-', $menutype);
            $candidates[] = str_replace('-', '_', $menutype);
            $candidates   = array_merge($candidates, $aliases);
        }

        return array_values(array_unique(array_filter($candidates, static function ($candidate) {
            return $candidate !== '';
        })));
    }

    /**
     * Zwraca pierwszy menutype z podanej listy, który ma opublikowane pozycje.
     */
    private function resolveMenuType($menutype, array $fallbacks = [])
    {
        $candidates = array_values(array_unique(array_merge([$menutype], $fallbacks)));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (!empty($this->loadMenuItems($candidate, 1, false))) {
                return $candidate;
            }
        }

        return $menutype;
    }

    /**
     * Wspólne pobieranie pozycji menu z rozwiązywaniem adresów SEF.
     */
    private function loadMenuItems($menutype, $limit = 20, $topLevel = false)
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([
                'm.id', 'm.title', 'm.alias', 'm.menutype', 'm.path', 'm.link',
                'm.type', 'm.parent_id', 'm.level', 'm.browserNav', 'm.params',
                'm.home', 'm.template_style_id',
            ])
            ->from($db->quoteName('#__menu', 'm'))
            ->where($db->quoteName('m.published') . ' = 1')
            ->where($db->quoteName('m.client_id') . ' = 0')
            ->where($db->quoteName('m.menutype') . ' = ' . $db->quote($menutype))
            ->order('m.lft ASC')
            ->setLimit($limit);

        if ($topLevel) {
            $query->where($db->quoteName('m.level') . ' = 1');
        }

        $db->setQuery($query);
        $rows  = $db->loadAssocList() ?: [];
        $root  = Uri::root();
        $items = [];

        foreach ($rows as $row) {
            $params = [];
            if (!empty($row['params'])) {
                $decoded = json_decode($row['params'], true);
                if (is_array($decoded)) {
                    $params = $decoded;
                }
            }

            $browserNav = (int) $row['browserNav'];
            $cssClass   = trim((string) ($params['menu-anchor_css'] ?? ''));
            $titleAttr  = trim((string) ($params['menu-anchor_title'] ?? ''));

            $items[] = [
                'id'         => (int) $row['id'],
                'title'      => $row['title'],
                'alias'      => $row['alias'],
                'menutype'   => $row['menutype'],
                'type'       => $row['type'],
                'link'       => $row['link'],
                'url'        => $this->resolveMenuLink($row),
                'target'     => $browserNav === 1 ? '_blank' : null,
                'level'      => (int) $row['level'],
                'parent_id'  => (int) $row['parent_id'],
                'home'       => (int) $row['home'] === 1,
                'image'      => $this->resolveMediaUrl($params['menu_image'] ?? '', $root),
                'css_class'  => $cssClass !== '' ? $cssClass : null,
                'title_attr' => $titleAttr !== '' ? $titleAttr : null,
            ];
        }

        return $items;
    }

    /**
     * Zamienia pole "link" z #__menu na adres użytkowy (SEF lub zewnętrzny).
     */
    private function resolveMenuLink(array $item)
    {
        $type = (string) ($item['type'] ?? '');
        $link = trim((string) ($item['link'] ?? ''));

        if ($link === '' || $link === '#' || in_array($type, ['separator', 'heading'], true)) {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $link)) {
            return $link;
        }

        if (strpos($link, 'index.php') === 0) {
            return $this->routeUrl($link);
        }

        return $link;
    }

    /**
     * Bezpieczne generowanie adresu SEF.
     */
    private function routeUrl($link)
    {
        try {
            return Route::_($link);
        } catch (\Throwable $e) {
            return $link;
        }
    }

    /**
     * Zamienia HTML z Joomla na czysty tekst (bez encji i podwójnych spacji).
     */
    private function plainText($html)
    {
        $text = strip_tags((string) $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Skraca tekst do podanej długości, nie przerywając wyrazu.
     */
    private function excerptText($text, $limit = 240)
    {
        $text = (string) $text;
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        $cut   = mb_substr($text, 0, $limit, 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($space !== false && $space > 0) {
            $cut = mb_substr($cut, 0, $space, 'UTF-8');
        }

        return rtrim($cut, " \t\n\r\0\x0B,.;:") . '…';
    }

    /**
     * Rozwiązuje ścieżkę obrazka (w tym format #joomlaImage://) na pełny URL.
     */
    private function resolveMediaUrl($path, $root)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        if (strpos($path, '#joomlaImage://') !== false) {
            $fragment = substr($path, strpos($path, '#joomlaImage://') + strlen('#joomlaImage://'));
            $fragment = explode('?', $fragment)[0];
            if (strpos($fragment, 'local-images/') === 0) {
                $fragment = 'images/' . substr($fragment, strlen('local-images/'));
            }
            $path = $fragment !== '' ? $fragment : explode('#', $path)[0];
        } elseif (strpos($path, 'local-images/') === 0) {
            $path = 'images/' . substr($path, strlen('local-images/'));
        }

        return rtrim($root, '/') . '/' . ltrim($path, '/');
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
            $input  = $this->app->input;

            // Kolejność: parametr żądania → konfiguracja komponentu → brak filtra.
            $catId = (int) $input->get('catid', 0, 'INT');
            if ($catId === 0) {
                $catId = (int) ($config->featured_article_catid ?? 0);
            }

            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            $query = $db->getQuery(true)
                ->select([
                    'a.id', 'a.title', 'a.alias', 'a.catid', 'a.introtext',
                    'a.fulltext', 'a.images', 'a.created', 'a.modified',
                    'a.hits', 'a.featured', 'a.created_by_alias',
                    'c.title as category_title', 'c.alias as category_alias',
                    'u.name as author_name',
                ])
                ->from($db->quoteName('#__content', 'a'))
                ->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
                ->where($db->quoteName('a.state') . ' = 1')
                ->where($db->quoteName('a.access') . ' = 1')
                ->where('(' . $db->quoteName('a.publish_up') . ' IS NULL OR ' . $db->quoteName('a.publish_up') . ' <= ' . $db->quote($now) . ')')
                ->where('(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' . $db->quoteName('a.publish_down') . ' = ' . $db->quote('0000-00-00 00:00:00') . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $db->quote($now) . ')')
                ->order('a.created DESC, a.id DESC')
                ->setLimit(1);

            if ($catId > 0) {
                $query->where($db->quoteName('a.catid') . ' = ' . $catId);
            }

            $db->setQuery($query);
            $article = $db->loadAssoc();

            if ($article) {
                $images = [];
                if (!empty($article['images'])) {
                    $decoded = json_decode($article['images'], true);
                    if (is_array($decoded)) {
                        $images = $decoded;
                    }
                }

                $root = Uri::root();
                $author = trim((string) ($article['created_by_alias'] ?: $article['author_name']));

                $article['id']              = (int) $article['id'];
                $article['catid']           = (int) $article['catid'];
                $article['hits']            = (int) $article['hits'];
                $article['featured']        = (int) $article['featured'];
                $article['author']          = $author !== '' ? $author : null;

                $intro = $this->plainText($article['introtext'] ?? '');
                if ($intro === '') {
                    $intro = $this->plainText($article['fulltext'] ?? '');
                }
                $article['excerpt'] = $this->excerptText($intro, 240);

                $article['image_intro']     = $this->resolveMediaUrl($images['image_intro'] ?? '', $root);
                $article['image_fulltext']  = $this->resolveMediaUrl($images['image_fulltext'] ?? '', $root);
                $article['url']             = $this->routeUrl('index.php?option=com_content&view=article&id=' . $article['id'] . '&catid=' . $article['catid']);
                $article['category_url']    = !empty($article['category_title'])
                    ? $this->routeUrl('index.php?option=com_content&view=category&id=' . $article['catid'])
                    : null;
            }

            echo json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->sendError(500, $e->getMessage());
        }
        jexit();
    }

    // Pomocnicza metoda do pobierania konfiguracji
    private function getConfig()
    {
        $defaults = (object) [
            'slider_images' => '[]',
            'featured_article_catid' => 0,
        ];

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__thapi_config'))
                ->where($db->quoteName('id') . ' = 1');

            $config = $db->setQuery($query)->loadObject();

            // Fallback na domyślne, jeśli tabela nie istnieje lub jest pusta.
            return $config ?: $defaults;
        } catch (\Throwable $e) {
            return $defaults;
        }
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
<?php
// src/Model/ExportModel.php
namespace ThApi\Component\ThApi\Site\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

class ExportModel extends BaseDatabaseModel
{
    public function getExportData($categoryIds = null)
    {
        $db = $this->getDbo();
        
        // Pobierz kategorie z artykułami 
        $categories = $this->getCategoriesWithArticles($categoryIds);
        
        // Struktura zgodna z wzorcem JSON
        return [
//            'id' => 11,
//            'source_id' => 1,
//            'issue' => 'Argumenty',
            'categories' => $categories
        ];
    }

    private function getCategoriesWithArticles($categoryIds = null)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select([
                'c.id',
                'c.title AS category'
            ])
            ->from($db->quoteName('#__categories', 'c'))
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_content'));

        // Filtruj po konkretnych kategoriach jeśli podano
        if (!empty($categoryIds)) {
            $query->where($db->quoteName('c.id') . ' IN (' . implode(',', $categoryIds) . ')');
        }

        $query->order('c.lft ASC');

        $db->setQuery($query);
        $categories = $db->loadAssocList();

        if (empty($categories)) {
            return [];
        }

        // Dla każdej kategorii pobierz najnowszy artykuł
        foreach ($categories as &$category) {
            $category['articles'] = $this->getNewestArticle($category['id']);
        }

        return $categories;
    }

    private function getNewestArticle($categoryId)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select([
                'a.id',
                'a.introtext AS intro',
                'COALESCE(a.created_by_alias, u.name) AS author',
                'a.created',
                'a.alias AS link',
                'a.title'
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.catid') . ' = ' . (int)$categoryId)
            ->order('a.id DESC')
            ->setLimit(1);

        $db->setQuery($query);
        $article = $db->loadAssoc();

        if ($article) {
            // Oczyść HTML i przygotuj dane
            $article['intro'] = chunk_split(base64_encode($article['intro']));//strip_tags($article['intro']);
            $article['link'] = $this->generateArticleUrl($article['id'], $article['link']);
            
            return [$article]; // Zwróć jako tablicę artykułów
        }

        return [];
    }

    private function generateArticleUrl($articleId, $alias)
    {
        // Generuj URL do artykułu - dostosuj do swojej struktury URL
//        return Factory::getApplication()->get('sitename') . '/index.php?option=com_content&view=article&id=' . $articleId;
        return Factory::getApplication()->get('sitename') . '/' . $articleId;
    }
}
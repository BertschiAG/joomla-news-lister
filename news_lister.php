<?php

defined('_JEXEC') or die;

/**
 * Created by PhpStorm.
 * Copyright: Bertschi AG, 2015
 * User: jbaumann
 * File: news_lister.php
 * Date: 03.11.2015
 * Time: 12:12
 *
 * @author Janik Baumann
 * @copyright Bertschi AG, 2015
 * @license ./LICENSE GNU General Public License version 3
 */
class plgContentNews_Lister extends JPlugin
{
    private $_regex = '/{newslist\s(.*)}/i';

    private $_arguments = array();

    private $_default_options = array(
        'template' => 'default',
        'categories' => '',
        'offset' => 0,
        'limit' => 0,
    );

    private $_categories = array();

    private $_articles = array();

    private $_content = array();

    private $_allowed_hooks = array(
        'nl',
        'article',
        'category',
    );

    /**
     * Plugin that loads a list of articles within content
     *
     * @param   string $pContext The context of the content being passed to the plugin.
     * @param   object &$pArticle The article object. Note $article->text is also available
     * @param   mixed &$pParams The article params
     * @param   integer $pPage The 'page' number
     *
     * @return  mixed   true if there is an error. Void otherwise.
     *
     */
    public function onContentPrepare($pContext, &$pArticle, &$pParams, $pPage = 0)
    {
        if (!$this->_shouldRun($pContext) && !$this->_wasTriggered($pArticle)) {
            return true;
        }
        if (!$this->_prepareArguments($pArticle)) {
            return true;
        }
        define('NL_ROOT', dirname(__FILE__));
        $this->_importClasses();
        $this->_selectCategories();
        $this->_selectArticles();
        $this->_prepareContent();

        $this->_replaceContext($pArticle);
        return true;
    }

    private function _importClasses()
    {
        if (!class_exists('ContentPreparer')) require(NL_ROOT . '/engines/ContentPreparer.php');
    }

    /**
     * Check if the plugin should run
     *
     * @param &$pContext
     *
     * @return bool True if the plugin should run
     */
    private function _shouldRun(&$pContext)
    {
        if ($pContext == 'com_content.indexer') {
            return false;
        }
        return true;
    }

    private function _wasTriggered(&$pArticle)
    {
        if (strpos($pArticle->text, 'newslist') === false) {
            return true;
        }
        return false;
    }

    private function _prepareArguments(&$pArticle)
    {
        preg_match_all($this->_regex, $pArticle->text, $matches, PREG_SET_ORDER);
        if ($matches) {
            foreach ($matches as $match) {
                $tmp = json_decode($match[1], true);
                if (!isset($tmp['template']) || !is_array($tmp['categories'])) {
                    return false;
                }
                $this->_arguments[] = $tmp;
            }
        } else {
            return false;
        }
        return true;
    }

    private function _selectCategories()
    {
        foreach ($this->_arguments as $pValue) {
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            $query->select('*');
            $query->from($db->quoteName('#__categories'));
            $query->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'));
            $query->where($db->quoteName('path') . ' REGEXP ' . $db->quote('^' . implode('(.*)$|^', $pValue['categories']) . '(.*)$'));
            $query->where($db->quoteName('published') . ' = ' . $db->quote('1'));
            $query->where('(' . $db->quoteName('language') . ' = ' . $db->quote(JFactory::getLanguage()->getTag()) . ' OR ' . $db->quoteName('language') . ' = ' . $db->quote('*') . ')');
            $query->order($db->quoteName('title') . ' ASC');
            $db->setQuery($query);
            $this->_categories[] = $db->loadObjectList();
        }
    }

    private function _selectArticles()
    {
        foreach ($this->_categories as $pKey => $pPass) {
            $categories = null;
            foreach ($pPass as $pValue) {
                $categories[] = $pValue->id;
            }
            if ($categories != null) {
                $db = JFactory::getDbo();
                $query = $db->getQuery(true);
                $limit = (isset($this->_arguments[$pKey]['limit']) && is_numeric($this->_arguments[$pKey]['limit'])) ? $this->_arguments[$pKey]['limit'] : $this->_default_options['limit'];
                $offset = (isset($this->_arguments[$pKey]['offset']) && is_numeric($this->_arguments[$pKey]['offset'])) ? $this->_arguments[$pKey]['offset'] : $this->_default_options['offset'];
                $query
                    ->select('*')
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('catid') . ' REGEXP ' . $db->quote('^' . implode('$|^', $categories) . '$'))
                    ->where($db->quoteName('state') . ' = ' . $db->quote('1'))
                    ->order($db->quoteName('created') . ' DESC');
                $db->setQuery($query, $offset, $limit);
                $this->_articles[] = $db->loadObjectList();
            }
        }
    }

    private function _prepareContent()
    {
        $cp = new ContentPreparer($this->_allowed_hooks, $this->_default_options);
        foreach ($this->_arguments as $pArgumentKey => $pArgumentValues) {
            $cp->newValues($this->_categories[$pArgumentKey], $pArgumentValues);
            foreach ($this->_articles[$pArgumentKey] as $pArticleValues) {
                $this->_content[] = $cp->prepare($pArticleValues);
            }
        }
    }

    private function _replaceContext(&$pArticle)
    {
        $pArticle->text = preg_replace($this->_regex, implode('', $this->_content), $pArticle->text);
    }
}
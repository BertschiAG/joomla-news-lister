<?php

defined('_JEXEC') or die;

/**
 * Created by PhpStorm.
 * Copyright: Bertschi AG, 2015
 * User: jbaumann
 * File: ContentPreparer.php
 * Date: 18.11.2015
 * Time: 09:29
 *
 * @author Janik Baumann
 * @copyright Bertschi AG, 2015
 * @license ./LICENSE GNU General Public License version 3
 */
class ContentPreparer
{
    /**
     * The article for the current preparation.
     *
     * @var object
     */
    private $_article;

    /**
     * All categories for the current preparation.
     *
     * @var object[]
     */
    private $_categories;

    /**
     * All arguments for the current preparation.
     *
     * @var array
     */
    private $_arguments_given;

    /**
     * The default arguments.
     *
     * @var array
     */
    private $_arguments_default;

    /**
     * The template which contains at the end the parsed template.
     *
     * @var string
     */
    private $_template;

    /**
     * The requested data which is used in the template.
     *
     * @var array
     */
    private $_xml;

    /**
     * The path to the requested template.
     *
     * @var string
     */
    private $_path_template;

    /**
     * The path to the requested template configuration.
     *
     * @var string
     */
    private $_path_xml;

    /**
     * All allowed hooks.
     *
     * @var array
     */
    private $_hooks_allowed;

    /**
     * All none parsed hooks.
     *
     * @var array
     */
    private $_hooks_raw;

    /**
     * All prepared hooks.
     *
     * @var array
     */
    private $_hooks_prepared;

    /**
     * The values for each hook.
     *
     * @var array
     */
    private $_hooks_value;

    /**
     * The name of the requested template.
     *
     * @var string
     */
    private $_template_name;

    /**
     * The none parsed template.
     *
     * @var string
     */
    private $_template_raw;

    /**
     * ContentPreparer constructor.
     *
     * @param array $pAllowedHooks The hooks which are allowed to use.
     * @param array $pDefaultArguments The default values of the arguments.
     */
    public function __construct($pAllowedHooks, $pDefaultArguments)
    {
        $this->_hooks_allowed = $pAllowedHooks;
        $this->_arguments_default = $pDefaultArguments;
    }

    /**
     * Resets the most variables and fills them with the over given values.
     *
     * @param object[] $pCategories All categories which are needed for the current preparation.
     * @param array $pArguments All arguments which are needed for the current preparation.
     * @return bool Returns true if everything succeed, false otherwise.
     */
    public function newValues($pCategories, $pArguments)
    {
        $this->_categories = $pCategories;
        $this->_arguments_given = $pArguments;
        $this->_template = null;
        $this->_path_template = null;
        $this->_path_xml = null;
        $this->_hooks_raw = null;
        $this->_hooks_prepared = null;
        $this->_hooks_value = null;
        $this->_getPaths();
        if ($this->_path_template == null) {
            return false;
        }
        $this->_getTemplate();
        $this->_getXml();
        $this->_getHooks();
        $this->_prepareHooks();
        return true;
    }

    /**
     * Starts preparing a single article.
     *
     * @param object $pArticle The article which should be prepared.
     * @return string The parsed template.
     */
    public function prepare($pArticle)
    {
        $this->_setArticle($pArticle);
        $this->_clearTemplate();
        $this->_getValuesForHooks();
        $this->_replaceHooksInTemplate();
        return $this->_template;
    }

    /**
     * Sets an new article for preparation.
     *
     * @param object $pArticle The article which should be prepared.
     */
    private function _setArticle($pArticle)
    {
        $this->_article = $pArticle;
    }

    /**
     * Starts all functions which register paths.
     */
    private function _getPaths()
    {
        $this->_getTemplateName();
        $this->_getTemplatePath();
        $this->_getXmlPath();
    }

    /**
     * Register the template name.
     */
    private function _getTemplateName()
    {
        $this->_template_name = (isset($this->_arguments_given['template'])) ? $this->_arguments_given['template'] : $this->_arguments_default['template'];
    }

    /**
     * Registers the template path.
     */
    private function _getTemplatePath()
    {
        if (file_exists(NL_ROOT . '/templates/' . $this->_template_name . '.html')) {
            $templatePath = NL_ROOT . '/templates/' . $this->_template_name . '.html';
        } elseif (file_exists(NL_ROOT . '/templates/' . $this->_arguments_default['template'] . '.html')) {
            $templatePath = NL_ROOT . '/templates/' . $this->_arguments_default['template'] . '.html';
        } else {
            $templatePath = null;
        }
        $this->_path_template = $templatePath;
    }

    /**
     * Registers the xml path.
     */
    private function _getXmlPath()
    {
        if (file_exists(NL_ROOT . '/templates/' . $this->_template_name . '.xml')) {
            $xmlPath = NL_ROOT . '/templates/' . $this->_template_name . '.xml';
        } elseif (file_exists(NL_ROOT . '/templates/' . $this->_arguments_default['template'] . '.xml')) {
            $xmlPath = NL_ROOT . '/templates/' . $this->_arguments_default['template'] . '.xml';
        } else {
            $xmlPath = null;
        }
        $this->_path_xml = $xmlPath;
    }

    /**
     * Gets the requested template and stores it for parsing and raw storage.
     */
    private function _getTemplate()
    {
        $this->_template_raw = file_get_contents($this->_path_template);
        $this->_template = $this->_template_raw;
    }

    /**
     * Gets the xml of the requested template.
     */
    private function _getXml()
    {
        $this->_xml = json_decode(json_encode(simplexml_load_file($this->_path_xml)), true);
    }

    /**
     * Clears the template for a new preparation.
     */
    private function _clearTemplate()
    {
        $this->_template = $this->_template_raw;
    }

    /**
     * Returns all hooks which are used in the template.
     */
    private function _getHooks()
    {
        preg_match_all('/{(.*?)}/', $this->_template, $hooks);
        $this->_hooks_raw = $hooks[1];
    }

    /**
     * Prepares all requested hooks.
     */
    private function _prepareHooks()
    {
        $this->_hooks_prepared = null;
        foreach ($this->_hooks_raw as $pHookValue) {
            $hook = explode('.', $pHookValue);
            if (in_array($hook[0], $this->_hooks_allowed)) {
                $this->_hooks_prepared[] = $hook;
            }
        }
    }

    /**
     * Routes the categories of hooks.
     */
    private function _getValuesForHooks()
    {
        $this->_hooks_value = null;
        foreach ($this->_hooks_prepared as $pHookValues) {
            switch ($pHookValues[0]) {
                case 'article':
                    $this->_addArticleValueForHook($pHookValues);
                    break;
                case 'category':
                    $this->_addCategoryValueForHook($pHookValues);
                    break;
                case 'nl':
                    $this->_addNewsListerValueForHook($pHookValues);
                    break;
                default:
                    $this->_addValueForHook('');
                    break;
            }
        }
    }

    /**
     * Adds an article information as replacement for a hook.
     *
     * @param array $pHookValues The exploded requested hook.
     */
    private function _addArticleValueForHook($pHookValues)
    {
        $value = $this->_article->$pHookValues[1];
        if (isset($pHookValues[2])) {
            $value = json_decode($value)->$pHookValues[2];
        }
        $value = $this->_formatValue($pHookValues, $value);
        $this->_addValueForHook($value);
    }

    /**
     * Adds a category information as replacement for a hook.
     *
     * @param array $pHookValues The exploded requested hook.
     */
    private function _addCategoryValueForHook($pHookValues)
    {
        $value = '';
        foreach ($this->_categories as $pCategoryKey => $pCategoryValue) {
            if ($pCategoryValue->id == $this->_article->catid) {
                $value = $pCategoryValue->$pHookValues[1];
            }
        }
        if (isset($pHookValues[2])) {
            $value = json_decode($value)->$pHookValues[2];
        }
        $this->_addValueForHook($value);
    }

    /**
     * Adds a result of an own function.
     *
     * @param array $pHookValues The exploded requested hook.
     */
    private function _addNewsListerValueForHook($pHookValues)
    {
        switch ($pHookValues[1]) {
            case 'link':
                $value = JRoute::_(ContentHelperRoute::getArticleRoute($this->_article->id, $this->_article->catid, $this->_article->language));
                $this->_addValueForHook($value);
                break;
            case 'word':
                $value = $this->_arguments_given['words'][$pHookValues[2]];
                $this->_addValueForHook($value);
                break;
            default:
                $this->_addValueForHook('');
                break;
        }
    }

    /**
     * Formats an value from database output to in configuration requested format.
     *
     * @param array $pHookValues The exploded requested hook.
     * @param string $pValue The value which should be parsed.
     * @return string Return the parsed value.
     */
    private function _formatValue($pHookValues, $pValue)
    {
        $hookName = implode('.', $pHookValues);
        foreach ($this->_xml['hook'] as $pXmlValue) {
            if ($pXmlValue['name'] == $hookName) {
                switch ($pXmlValue['type']) {
                    case 'date':
                        $pValue = $this->_formatDateValue($pXmlValue['format']['src'], $pXmlValue['format']['end'], $pValue);
                        break;
                }
            }
        }
        return $pValue;
    }

    /**
     * Formats a date value to the wished format.
     *
     * @param string $pSrcFormat The source format of the value.
     * @param string $pEndFormat The end format of the value.
     * @param string $pValue The value which should be parsed.
     * @return string Return the formatted date value.
     */
    private function _formatDateValue($pSrcFormat, $pEndFormat, $pValue)
    {
        $date = DateTime::createFromFormat($pSrcFormat, $pValue);
        return $date->format($pEndFormat);
    }

    /**
     * Add the specified value as replacement for a hook.
     *
     * @param string $pValue The value which should be added.
     */
    private function _addValueForHook($pValue)
    {
        $pValue = preg_replace('/(<\?|\?>)/', '%60%63', $pValue);
        $this->_hooks_value[] = $pValue;
    }

    /**
     * Replaces all hooks in the template through it's value.
     */
    private function _replaceHooksInTemplate()
    {
        $hooks = $this->_hooks_raw;
        foreach ($hooks as &$pHook) {
            $pHook = '/{' . $pHook . '}/';
        }
        $this->_template = preg_replace($hooks, $this->_hooks_value, $this->_template);
    }

}
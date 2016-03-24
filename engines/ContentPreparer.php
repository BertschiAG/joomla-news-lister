<?php

/**
 * Created by PhpStorm.
 * Copyright: Bertschi AG, 2015
 * User: jbaumann
 * File: ContentPreparer.php
 * Date: 18.11.2015
 * Time: 09:29
 */
class ContentPreparer
{
    private $_article;
    private $_categories;
    private $_arguments_given;
    private $_arguments_default;
    private $_template;
    private $_xml;
    private $_path_template;
    private $_path_xml;
    private $_hooks_allowed;
    private $_hooks_raw;
    private $_hooks_prepared;
    private $_hooks_value;
    private $_template_name;
    private $_template_raw;

    public function __construct($pAllowedHooks, $pDefaultArguments)
    {
        $this->_hooks_allowed = $pAllowedHooks;
        $this->_arguments_default = $pDefaultArguments;
    }

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

    public function prepare($pArticle)
    {
        $this->_setArticle($pArticle);
        $this->_clearTemplate();
        $this->_getValuesForHooks();
        $this->_replaceHooksInTemplate();
        return $this->_template;
    }

    private function _setArticle($pArticle)
    {
        $this->_article = $pArticle;
    }

    private function _getPaths()
    {
        $this->_getTemplateName();
        $this->_getTemplatePath();
        $this->_getXmlPath();
    }

    private function _getTemplateName()
    {
        $this->_template_name = (isset($this->_arguments_given['template'])) ? $this->_arguments_given['template'] : $this->_arguments_default['template'];
    }

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

    private function _getTemplate()
    {
        $this->_template_raw = file_get_contents($this->_path_template);
        $this->_template = $this->_template_raw;
    }

    private function _getXml()
    {
        $this->_xml = json_decode(json_encode(simplexml_load_file($this->_path_xml)), true);
    }


    private function _clearTemplate()
    {
        $this->_template = $this->_template_raw;
    }

    private function _getHooks()
    {
        preg_match_all('/{(.*?)}/', $this->_template, $hooks);
        $this->_hooks_raw = $hooks[1];
    }

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

    private function _addArticleValueForHook($pHookValues)
    {
        $value = $this->_article->$pHookValues[1];
        if (isset($pHookValues[2])) {
            $value = json_decode($value)->$pHookValues[2];
        }
        $value = $this->_formatValue($pHookValues, $value);
        $this->_addValueForHook($value);
    }

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

    private function _formatDateValue($pSrcFormat, $pEndFormat, $pValue)
    {
        $date = DateTime::createFromFormat($pSrcFormat, $pValue);
        return $date->format($pEndFormat);
    }

    private function _addValueForHook($pValue)
    {
        $pValue = preg_replace('/(<\?|\?>)/', '%60%63', $pValue);
        $this->_hooks_value[] = $pValue;
    }

    private function _replaceHooksInTemplate()
    {
        $hooks = $this->_hooks_raw;
        foreach ($hooks as &$pHook) {
            $pHook = '/{' . $pHook . '}/';
        }
        $this->_template = preg_replace($hooks, $this->_hooks_value, $this->_template);
    }

}
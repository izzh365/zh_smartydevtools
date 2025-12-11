<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

require_once dirname(__FILE__) . '/../Contracts/TagProcessorInterface.php';
require_once dirname(__FILE__) . '/../Utils/CommonUtils.php';

/**
 * 简单标签处理器
 *
 * 处理单标签,无闭合标签的简单 Smarty 标签
 * 如: {extends}, {hook}, {widget}
 *
 * 特点:
 * - 无闭合标签
 * - 参数相对简单(不包含复杂嵌套)
 * - 使用正则表达式直接匹配(支持跨行)
 */
class SimpleTagProcessor implements TagProcessorInterface
{
    /**
     * @var array 支持的标签配置
     */
    protected $tagConfigs = [
        'extends' => [
            'pattern' => '/\{extends\s+file=([\'"])([^\'"]+)\1([^\}]*)\}/s',
            'label_callback' => 'extractExtendsLabel',
        ],
        'hook' => [
            'pattern' => '/\{hook\s+h=([\'"])([^\'"]+)\1([^\}]*)\}/s',
            'label_callback' => 'extractHookLabel',
        ],
        'widget' => [
            'pattern' => '/\{widget\s+name=([\'"])([^\'"]+)\1([^\}]*)\}/s',
            'label_callback' => 'extractWidgetLabel',
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function process($source, $smarty, $cleanSource)
    {
        $currentTemplate = CommonUtils::getCurrentTemplatePath($smarty);
        $safeTemplate = CommonUtils::sanitizePathForComment($currentTemplate);

        foreach ($this->tagConfigs as $tagName => $config) {
            $source = preg_replace_callback($config['pattern'], function ($matches) use ($config, $safeTemplate, $cleanSource, $smarty, $currentTemplate) {
                // 检查是否在注释中
                if (CommonUtils::isTagInComment($matches[0], $cleanSource)) {
                    return $matches[0];
                }

                // 提取标签标识符
                $label = call_user_func([$this, $config['label_callback']], $matches);
                $tagNameUpper = strtoupper(str_replace('_', ' ', array_search($config, $this->tagConfigs)));

                // 为 extends 使用不同的注释格式(不需要 START/END),包含当前模板路径
                // 同时保存到全局变量供后续使用
                if ($tagNameUpper === 'EXTENDS') {
                    // 尝试解析变量形式的父模板路径 (如 {extends file=$layout})
                    $parentPath = $label;
                    if (strpos($label, '$') === 0) {
                        // 变量形式,尝试从 Smarty 获取实际值
                        $varName = substr($label, 1);
                        try {
                            $tplVars = $smarty->getTemplateVars();
                            if (isset($tplVars[$varName])) {
                                $parentPath = $tplVars[$varName];
                            }
                        } catch (Exception $e) {
                            // 获取失败,保持原变量名
                        }
                    }

                    // 保存继承关系到全局数组
                    if (!isset($GLOBALS['smarty_extends_chain'])) {
                        $GLOBALS['smarty_extends_chain'] = [];
                    }
                    $GLOBALS['smarty_extends_chain'][] = [
                        'parent' => $parentPath,
                        'child' => $currentTemplate,
                    ];

                    return "<!-- EXTENDS: {$parentPath} (from: {$safeTemplate}) -->\n" . $matches[0];
                }

                // 其他标签使用 START/END 格式
                return "<!-- START {$tagNameUpper}: {$label} (in: {$safeTemplate}) -->\n" .
                    $matches[0] .
                    "\n<!-- END {$tagNameUpper}: {$label} -->";
            }, $source);
        }

        return $source;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTags()
    {
        return array_keys($this->tagConfigs);
    }

    /**
     * {@inheritdoc}
     */
    public function getTagType()
    {
        return 'simple';
    }

    /**
     * 提取 extends 标签的父模板名称
     *
     * @param array $matches 正则匹配结果
     * @return string 父模板名称
     */
    protected function extractExtendsLabel($matches)
    {
        return $matches[2]; // file 参数的值
    }

    /**
     * 提取 hook 标签的钩子名称
     *
     * @param array $matches 正则匹配结果
     * @return string 钩子名称
     */
    protected function extractHookLabel($matches)
    {
        return $matches[2]; // h 参数的值
    }

    /**
     * 提取 widget 标签的组件名称
     *
     * @param array $matches 正则匹配结果
     * @return string 组件名称
     */
    protected function extractWidgetLabel($matches)
    {
        return $matches[2]; // name 参数的值
    }
}

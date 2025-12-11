<?php

/**
 * Smarty 开发注释处理器
 *
 * 负责在模板编译时注入开发注释,帮助开发者识别模板的继承、包含、块等结构。
 * 使用工厂模式管理不同类型的标签处理器。
 *
 * @author    Smarty Dev Tools
 * @copyright 2023 Smarty Dev Tools
 * @license   Academic Free License (AFL 3.0)
 * @version   2.0.0 (Refactored with Factory Pattern)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// 加载必需的类文件
require_once dirname(__FILE__) . '/TagProcessorFactory.php';
require_once dirname(__FILE__) . '/StructureVisualizer.php';

class SmartyDevProcessor
{
    /**
     * 处理 Smarty 模板中的所有标签,添加开发注释
     *
     * 使用工厂模式统一调度所有标签处理器:
     * - extends, hook, widget (SimpleTagProcessor)
     * - include (ComplexTagProcessor)
     * - block (BlockTagProcessor)
     * - eval (EvalTagProcessor)
     *
     * @param string $source 模板源代码
     * @param Smarty $smarty Smarty 实例
     * @return string 处理后的模板代码
     */
    public static function processDevComments($source, $smarty)
    {
        try {
            // 只在开发模式下处理
            if (!Configuration::get('SMARTY_DEV_TOOLS_ENABLED')) {
                return $source;
            }

            // 准备清理后的源代码(移除注释)供处理器使用
            $cleanSource = CommonUtils::cleanSource($source);

            // 获取所有标签处理器并依次处理
            $processors = TagProcessorFactory::getProcessors();

            foreach ($processors as $processor) {
                $source = $processor->process($source, $smarty, $cleanSource);
            }

            return $source;
        } catch (Exception $e) {
            // 静默失败,记录错误但返回原始源代码
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog(
                    'Smarty Dev Tools Processing Error: ' . $e->getMessage(),
                    3,
                    null,
                    null,
                    null,
                    true
                );
            }
            return $source;
        }
    }

    /**
     * 输出过滤器，在页面底部添加模板结构可视化工具
     *
     * 委托给 StructureVisualizer 类处理
     *
     * @param string $output HTML 输出
     * @param Smarty $smarty Smarty 实例
     * @return string 修改后的 HTML 输出
     */
    public static function addTemplateStructureViewer($output, $smarty)
    {
        return StructureVisualizer::addTemplateStructureViewer($output, $smarty);
    }
}

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

/**
 * 通用工具类
 *
 * 提供可复用的工具方法,避免代码重复
 */
class CommonUtils
{
    /**
     * 查找匹配的大括号位置
     *
     * 精确处理嵌套大括号和字符串内的字符
     *
     * @param string $source 源代码
     * @param int $startPos 开始位置('{' 的位置)
     * @return int|false 匹配的 '}' 的位置,失败返回 false
     */
    public static function findMatchingBrace($source, $startPos)
    {
        $braceCount = 0;
        $length = strlen($source);
        $inString = false;
        $stringDelimiter = '';

        for ($i = $startPos; $i < $length; $i++) {
            $char = $source[$i];

            // 处理字符串内的字符(忽略大括号)
            if ($inString) {
                if ($char === $stringDelimiter && ($i === 0 || $source[$i - 1] !== '\\')) {
                    $inString = false;
                }
                continue;
            }

            // 检查是否进入字符串
            if ($char === '"' || $char === "'") {
                $inString = true;
                $stringDelimiter = $char;
                continue;
            }

            // 处理大括号
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    /**
     * 清理源代码,移除 Smarty 注释和 HTML 注释
     *
     * @param string $source 原始源代码
     * @return string 清理后的源代码
     */
    public static function cleanSource($source)
    {
        $cleanSource = $source;

        // 移除 Smarty 注释 {* ... *}
        $cleanSource = preg_replace('/\{\*.*?\*\}/s', '', $cleanSource);

        // 移除 HTML 注释 <!-- ... --> (避免干扰标签匹配)
        $cleanSource = preg_replace('/<!--.*?-->/s', '', $cleanSource);

        return $cleanSource;
    }

    /**
     * 检查标签是否在注释中
     *
     * @param string $tag 标签内容
     * @param string $cleanSource 清理后的源代码
     * @return bool true=在注释中, false=正常标签
     */
    public static function isTagInComment($tag, $cleanSource)
    {
        // 如果在 cleanSource 中找不到,说明在注释中
        return strpos($cleanSource, $tag) === false;
    }

    /**
     * 安全化路径用于 HTML 注释
     *
     * 处理反斜杠和可能导致注释中断的字符
     *
     * @param string $path 文件路径
     * @return string 安全的路径
     */
    public static function sanitizePathForComment($path)
    {
        // 替换反斜杠为正斜杠
        $path = str_replace('\\', '/', $path);

        // 替换可能中断 HTML 注释的 -- 序列
        $path = str_replace('--', '—', $path);

        return $path;
    }

    /**
     * 获取当前模板文件的绝对路径
     *
     * @param Smarty $smarty Smarty 实例
     * @return string 模板绝对路径
     */
    public static function getCurrentTemplatePath($smarty)
    {
        // 默认值
        $path = 'unknown_template';

        // 方法1: 通过 Smarty 的 _source->filepath 获取(最可靠,优先)
        if (isset($smarty->_source) && isset($smarty->_source->filepath) && $smarty->_source->filepath) {
            return $smarty->_source->filepath;
        }

        // 方法2: 通过 template_resource 获取(需特殊处理 eval: 开头的情况)
        if (isset($smarty->template_resource) && $smarty->template_resource) {
            $resource = $smarty->template_resource;

            // 如果是 eval: 开头(Smarty 把内联模板标记为 eval:...),尝试从继承栈找真实文件
            if (strpos($resource, 'eval:') === 0) {
                // 尝试从继承栈中寻找真实的父模板文件路径(最后一个非空 filepath)
                if (
                    isset($smarty->_inheritance) &&
                    isset($smarty->_inheritance->sources) &&
                    !empty($smarty->_inheritance->sources)
                ) {
                    $sources = $smarty->_inheritance->sources;
                    // 从后往前找最接近的有 filepath 的源
                    for ($i = count($sources) - 1; $i >= 0; $i--) {
                        $src = $sources[$i];
                        if (is_object($src) && isset($src->filepath) && $src->filepath) {
                            return $src->filepath;
                        }
                        // 有些 Smarty 版本可能把 resource 放在 resource 字段
                        if (is_object($src) && isset($src->resource) && $src->resource && strpos($src->resource, 'eval:') !== 0) {
                            return $src->resource;
                        }
                    }
                }

                // 如果没有继承栈信息,尝试从 smarty 对象中找其他线索
                if (isset($smarty->_current_file) && $smarty->_current_file) {
                    return str_replace('\\', '/', $smarty->_current_file);
                }

                // 回退: 返回 eval 标签
                return 'eval_template';
            }

            // 非 eval 的 resource,可能是相对路径,尝试解析为绝对路径
            $path = $resource;

            // 移除 file: 前缀
            if (strpos($path, 'file:') === 0) {
                $path = substr($path, 5);
            }

            // 如果不是绝对路径,尝试查找
            if (strpos($path, ':/') === false && strpos($path, DIRECTORY_SEPARATOR) !== 0) {
                $absolutePath = self::findAbsolutePath($path, $smarty);
                if ($absolutePath !== $path) {
                    $path = $absolutePath;
                }
            }

            return $path;
        }

        // 方法3: 通过继承栈获取(作为后备)
        if (
            isset($smarty->_inheritance) &&
            !empty($smarty->_inheritance->sources)
        ) {
            $sources = $smarty->_inheritance->sources;
            $lastSource = end($sources);
            if (isset($lastSource->filepath)) {
                return $lastSource->filepath;
            }
            if (isset($lastSource->resource)) {
                return $lastSource->resource;
            }
        }

        return $path;
    }

    /**
     * 查找模板文件的绝对路径
     *
     * @param string $file 文件路径
     * @param Smarty $smarty Smarty 实例
     * @return string 绝对路径
     */
    public static function findAbsolutePath($file, $smarty)
    {
        // 1. 检查是否是模块文件 (module: 语法)
        if (strpos($file, 'module:') === 0) {
            return self::findModuleAbsolutePath($file);
        }

        // 2. 检查当前主题的 modules/ 目录下的覆盖
        $themeModulesPath = _PS_THEME_DIR_ . 'modules/' . $file;
        if (file_exists($themeModulesPath)) {
            return $themeModulesPath;
        }

        // 3. 检查父主题的 modules/ 目录下的覆盖
        if (_PS_PARENT_THEME_DIR_) {
            $parentThemeModulesPath = _PS_PARENT_THEME_DIR_ . 'modules/' . $file;
            if (file_exists($parentThemeModulesPath)) {
                return $parentThemeModulesPath;
            }
        }

        // 4. 检查模块自身的模板目录 (适用于非 module: 语法的普通路径)
        $modulePath = self::findInModuleDirs($file);
        if ($modulePath) {
            return $modulePath;
        }

        // 5. 检查 Smarty 的模板目录(通常是主题的 templates/ 目录)
        $templateDirs = $smarty->getTemplateDir();
        if (is_array($templateDirs)) {
            foreach ($templateDirs as $dir) {
                $fullPath = $dir . $file;
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
        } else {
            $fullPath = $templateDirs . $file;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // 6. 检查父主题的模板目录
        if (_PS_PARENT_THEME_DIR_) {
            $parentPath = _PS_PARENT_THEME_DIR_ . 'templates/' . $file;
            if (file_exists($parentPath)) {
                return $parentPath;
            }
        }

        // 如果都找不到,返回原始路径
        return $file;
    }

    /**
     * 为 module: 语法查找绝对路径
     *
     * @param string $file module: 格式的文件路径
     * @return string 绝对路径
     */
    protected static function findModuleAbsolutePath($file)
    {
        $moduleFile = str_replace('module:', '', $file);
        list($moduleName, $moduleTemplate) = explode('/', $moduleFile, 2);

        // 优先检查主题的 modules/ 目录下对该模块模板的覆盖
        $themeOverridePath = _PS_THEME_DIR_ . 'modules/' . $moduleName . '/' . $moduleTemplate;
        if (file_exists($themeOverridePath)) {
            return $themeOverridePath;
        }

        // 检查父主题的覆盖
        if (_PS_PARENT_THEME_DIR_) {
            $parentThemeOverridePath = _PS_PARENT_THEME_DIR_ . 'modules/' . $moduleName . '/' . $moduleTemplate;
            if (file_exists($parentThemeOverridePath)) {
                return $parentThemeOverridePath;
            }
        }

        // 最后检查模块自身的模板目录
        $moduleDir = _PS_MODULE_DIR_ . $moduleName . '/';
        $pathsToCheck = [
            $moduleDir . 'views/templates/' . $moduleTemplate,
            $moduleDir . $moduleTemplate,
        ];

        foreach ($pathsToCheck as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $file; // 如果找不到,返回原始路径
    }

    /**
     * 在模块目录中查找普通路径的模板文件
     *
     * @param string $file 文件路径
     * @return string|null 找到的绝对路径或 null
     */
    protected static function findInModuleDirs($file)
    {
        // 如果文件路径已经包含 modules,尝试直接定位
        if (preg_match('#^modules/([^/]+)/(.*)$#', $file, $matches)) {
            $moduleName = $matches[1];
            $relativePath = $matches[2];

            $pathsToCheck = [
                _PS_MODULE_DIR_ . $moduleName . '/' . $relativePath,
                _PS_MODULE_DIR_ . $moduleName . '/views/templates/' . $relativePath,
            ];

            foreach ($pathsToCheck as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}

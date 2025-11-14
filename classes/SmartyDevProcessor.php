<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with the package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 *
 *
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

class SmartyDevProcessor
{
    protected static $blockStack = array();
    protected static $conditionStack = array();

    /**
     * 处理Smarty模板中的include和block标签，添加路径注释
     *
     * @param string $source 模板源代码
     * @param Smarty $smarty Smarty实例
     * @return string 处理后的模板代码
     */
    public static function processDevComments($source, $smarty)
    {
        // 只在开发模式下处理
        if (!Configuration::get('SMARTY_DEV_TOOLS_ENABLED')) {
            return $source;
        }

        // 重置block栈
        self::$blockStack = array();

        // 先处理extends标签
        $source = self::processExtendsComments($source, $smarty);

        // 然后处理block标签
        $source = self::processBlockComments($source, $smarty);

        // 处理hook标签
        $source = self::processHookComments($source, $smarty);

        // 处理widget标签
        $source = self::processWidgetComments($source, $smarty);

        // 处理eval标签（新增）
        $source = self::processEvalComments($source, $smarty);

        // 然后处理include标签
        $source = self::processIncludeComments($source, $smarty);

        return $source;
    }

    /**
     * 处理extends标签注释和结构收集
     */
    protected static function processExtendsComments($source, $smarty)
    {
        // 匹配extends标签
        $pattern = '/\{extends\s+file=([\'"])([^\'"]+)\1([^\}]*)\}/';
        return preg_replace_callback($pattern, function ($matches) use ($smarty) {
            $parentTemplate = $matches[2];
            $params = $matches[3];

            // 返回带有注释的extends标签
            return "<!-- EXTENDS: {$parentTemplate} -->\n" .
                $matches[0];
        }, $source);
    }
    /**
     * 处理include标签注释和结构收集
     */
    protected static function processIncludeComments($source, $smarty)
    {
        // 先移除被注释掉的内容，避免处理被注释的include标签
        $cleanSource = preg_replace('/\{\*.*?\*\}/s', '', $source);

        // 使用逐字符解析的方式处理include标签，确保正确处理嵌套的大括号
        $result = '';
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            // 查找include标签的开始位置
            $startPos = strpos($source, '{include', $offset);

            // 如果没有更多include标签了，添加剩余内容并退出
            if ($startPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            // 添加当前位置到include标签之间的内容
            $result .= substr($source, $offset, $startPos - $offset);

            // 找到标签的结束位置（平衡大括号）
            $endPos = self::findMatchingBrace($source, $startPos);

            if ($endPos !== false) {
                $fullTag = substr($source, $startPos, $endPos - $startPos + 1);

                // 检查这个标签是否在注释中
                if (strpos($cleanSource, $fullTag) === false) {
                    // 如果在原始source中能找到但在cleanSource中找不到，说明在注释中
                    $result .= $fullTag;
                    $offset = $endPos + 1;
                    continue;
                }

                // 提取文件名
                if (preg_match('/file=([\'"])([^\'"]+)\1/', $fullTag, $fileMatches)) {
                    $file = $fileMatches[2];

                    // 获取文件的绝对路径
                    $absolutePath = self::findAbsolutePath($file, $smarty);
                    // 将路径中的反斜杠替换为正斜杠，避免HTML注释中的"--"问题
                    $safePath = str_replace('\\', '/', $absolutePath);

                    // 返回带有注释的include标签
                    $result .= "<!-- START INCLUDE: {$safePath} -->\n" .
                        $fullTag .
                        "\n<!-- END INCLUDE: {$safePath} -->";
                } else {
                    // 如果无法提取文件名，则不处理该标签
                    $result .= $fullTag;
                }

                $offset = $endPos + 1;
            } else {
                // 如果找不到结束位置，添加当前字符并继续
                $result .= $source[$startPos];
                $offset = $startPos + 1;
            }
        }

        return $result;
    }

    /**
     * 查找匹配的大括号位置
     */
    protected static function findMatchingBrace($source, $startPos)
    {
        $braceCount = 0;
        $length = strlen($source);
        $inString = false;
        $stringDelimiter = '';

        for ($i = $startPos; $i < $length; $i++) {
            $char = $source[$i];

            // 处理字符串内的字符（忽略大括号）
            if ($inString) {
                if ($char === $stringDelimiter && ($i === 0 || $source[$i-1] !== '\\')) {
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
     * 处理block标签注释和结构收集
     */
    protected static function processBlockComments($source, $smarty)
    {
        // 获取当前模板文件的绝对路径
        $currentTemplate = self::getCurrentTemplatePath($smarty);

        // 先移除被注释掉的内容，避免处理被注释的block标签
        $cleanSource = preg_replace('/\{\*.*?\*\}/s', '', $source);

        // 先处理自闭合的block标签 {block name='xxx'}{/block}
        $selfClosingPattern = '/\{block\s+name=([\'"])([^\'"]+)\1([^\}]*)\}\{\/block\}/';
        $source = preg_replace_callback($selfClosingPattern, function ($matches) use ($currentTemplate, $cleanSource) {
            // 检查这个标签是否在注释中
            if (strpos($cleanSource, $matches[0]) === false) {
                // 如果在原始source中能找到但在cleanSource中找不到，说明在注释中
                return $matches[0];
            }

            $blockName = $matches[2];
            $params = $matches[3];

            // 对于自闭合标签，直接添加开始和结束注释
            return "<!-- START BLOCK: {$blockName} (in: {$currentTemplate}) -->\n" .
                   "{block name='{$blockName}'{$params}}{/block}" .
                   "\n<!-- END BLOCK: {$blockName} (in: {$currentTemplate}) -->";
        }, $source);

        // 重置block栈
        self::$blockStack = array();

        // 使用更精确的方法处理开始和结束标签
        $result = '';
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            // 查找最近的开始或结束标签
            $startPos = strpos($source, '{block', $offset);
            $endPos = strpos($source, '{/block}', $offset);

            // 如果没有更多标签了，添加剩余内容并退出
            if ($startPos === false && $endPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            // 确定下一个要处理的标签位置
            if ($startPos === false) {
                $nextPos = $endPos;
                $isStart = false;
            } elseif ($endPos === false) {
                $nextPos = $startPos;
                $isStart = true;
            } else {
                $nextPos = min($startPos, $endPos);
                $isStart = ($nextPos == $startPos);
            }

            // 添加当前位置到下一个标签之间的内容
            $result .= substr($source, $offset, $nextPos - $offset);

            if ($isStart) {
                // 处理开始标签
                if (preg_match('/\{block\s+name=([\'"])([^\'"]+)\1([^\}]*)\}/', $source, $matches, 0, $nextPos)) {
                    $fullMatch = $matches[0];
                    $blockName = $matches[2];
                    $params = $matches[3];

                    // 检查这个标签是否在注释中
                    if (strpos($cleanSource, $fullMatch) === false) {
                        $result .= $fullMatch;
                        $offset = $nextPos + strlen($fullMatch);
                        continue;
                    }

                    // 检查是否是自闭合标签（这种情况应该已经被上面处理过了，但为了安全再检查一遍）
                    if (substr($source, $nextPos + strlen($fullMatch), 9) === '{/block}') {
                        // 这种情况应该不会发生，因为我们已经处理过了自闭合标签
                        $result .= $fullMatch;
                        $offset = $nextPos + strlen($fullMatch);
                    } else {
                        // 普通开始标签
                        self::$blockStack[] = array(
                            'name' => $blockName,
                            'template' => $currentTemplate
                        );

                        $result .= "<!-- START BLOCK: {$blockName} (in: {$currentTemplate}) -->\n" . $fullMatch;
                        $offset = $nextPos + strlen($fullMatch);
                    }
                } else {
                    // 不应该发生
                    $result .= substr($source, $nextPos, 1);
                    $offset = $nextPos + 1;
                }
            } else {
                // 处理结束标签
                // 检查这个标签是否在注释中
                if (strpos($cleanSource, '{/block}') === false) {
                    $result .= '{/block}';
                    $offset = $nextPos + 8; // '{/block}' 的长度是8
                    continue;
                }

                if (!empty(self::$blockStack)) {
                    $blockInfo = array_pop(self::$blockStack);
                    $blockName = $blockInfo['name'];
                    $template = $blockInfo['template'];

                    $result .= "{/block}\n<!-- END BLOCK: {$blockName} (in: {$template}) -->";
                } else {
                    // 没有匹配的开始标签
                    $result .= "{/block}\n<!-- END BLOCK -->";
                }
                $offset = $nextPos + 8; // '{/block}' 的长度是8
            }
        }

        return $result;
    }

    /**
     * 递归处理嵌套的block标签
     */
    protected static function processNestedBlocks($content, $currentTemplate)
    {
        // 使用更精确的正则表达式匹配嵌套的block标签及其内容
        $pattern = '/\{block\s+name=([\'"])([^\'"]+)\1([^\}]*)\}((?:.(?!\{block\s+name=[\'"][^\'"]+[\'"]\}))*.?)\{\/block\}/s';

        // 递归处理嵌套的block标签
        return preg_replace_callback($pattern, function ($matches) use ($currentTemplate) {
            $blockName = $matches[2];
            $params = $matches[3];
            $content = $matches[4];

            // 检查内容是否为空或只包含空白字符，判断是否为自闭合标签
            $isSelfClosing = (trim($content) === '');

            // 创建block节点
            $node = array(
                'name' => $blockName,
                'template' => $currentTemplate
            );

            // 如果不是自闭合标签，处理嵌套内容
            if (!$isSelfClosing) {
                // 递归处理内部的block标签
                $processedContent = self::processNestedBlocks($content, $currentTemplate);

                // 返回带有注释的block结构
                return "<!-- START BLOCK: {$blockName} (in: {$currentTemplate}) -->\n" .
                       "{block name='{$blockName}'{$params}}" .
                       $processedContent .
                       "{/block}\n<!-- END BLOCK: {$blockName} (in: {$currentTemplate}) -->";
            } else {
                // 返回带有注释的自闭合block标签
                return "<!-- START BLOCK: {$blockName} (in: {$currentTemplate}) -->\n" .
                       "{block name='{$blockName}'{$params}}{/block}" .
                       "\n<!-- END BLOCK: {$blockName} (in: {$currentTemplate}) -->";
            }
        }, $content);
    }

     /**
     * 处理hook标签注释
     */
    protected static function processHookComments($source, $smarty)
    {
        // 先移除被注释掉的内容，避免处理被注释的hook标签
        $cleanSource = preg_replace('/\{\*.*?\*\}/s', '', $source);

        // 匹配hook标签
        $pattern = '/\{hook\s+h=([\'"])([^\'"]+)\1([^\}]*)\}/';
        return preg_replace_callback($pattern, function ($matches) use ($smarty, $cleanSource) {
            // 检查这个标签是否在注释中
            if (strpos($cleanSource, $matches[0]) === false) {
                // 如果在原始source中能找到但在cleanSource中找不到，说明在注释中
                return $matches[0];
            }

            $hookName = $matches[2];
            $params = $matches[3];

            // 获取当前模板文件的绝对路径
            $currentTemplate = self::getCurrentTemplatePath($smarty);

            // 返回带有注释的hook标签
            return "<!-- START HOOK: {$hookName} (in: {$currentTemplate}) -->\n" .
                $matches[0] .
                "\n<!-- END HOOK: {$hookName} -->";
        }, $source);
    }

    /**
     * 处理widget标签注释
     */
    protected static function processWidgetComments($source, $smarty)
    {
        // 先移除被注释掉的内容，避免处理被注释的widget标签
        $cleanSource = preg_replace('/\{\*.*?\*\}/s', '', $source);

        // 匹配widget标签
        $pattern = '/\{widget\s+name=([\'"])([^\'"]+)\1([^\}]*)\}/';
        return preg_replace_callback($pattern, function ($matches) use ($smarty, $cleanSource) {
            // 检查这个标签是否在注释中
            if (strpos($cleanSource, $matches[0]) === false) {
                // 如果在原始source中能找到但在cleanSource中找不到，说明在注释中
                return $matches[0];
            }

            $widgetName = $matches[2];
            $params = $matches[3];

            // 获取当前模板文件的绝对路径
            $currentTemplate = self::getCurrentTemplatePath($smarty);

            // 返回带有注释的widget标签
            return "<!-- START WIDGET: {$widgetName} (in: {$currentTemplate}) -->\n" .
                $matches[0] .
                "\n<!-- END WIDGET: {$widgetName} -->";
        }, $source);
    }

    /**
     * 处理eval标签注释和结构收集（新增）
     */
    protected static function processEvalComments($source, $smarty)
    {
        // 先移除被注释掉的内容，避免处理被注释的eval标签
        $cleanSource = preg_replace('/\{\*.*?\*\}/s', '', $source);

        // 使用逐字符解析的方式处理eval标签，确保正确处理嵌套的大括号或包含字符串
        $result = '';
        $offset = 0;
        $length = strlen($source);

        // 获取当前模板文件的绝对路径
        $currentTemplate = self::getCurrentTemplatePath($smarty);

        while ($offset < $length) {
            // 查找eval标签的开始位置
            $startPos = strpos($source, '{eval', $offset);

            // 如果没有更多eval标签了，添加剩余内容并退出
            if ($startPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            // 添加当前位置到eval标签之间的内容
            $result .= substr($source, $offset, $startPos - $offset);

            // 找到标签的结束位置（平衡大括号）
            $endPos = self::findMatchingBrace($source, $startPos);

            if ($endPos !== false) {
                $fullTag = substr($source, $startPos, $endPos - $startPos + 1);

                // 检查这个标签是否在注释中
                if (strpos($cleanSource, $fullTag) === false) {
                    // 如果在原始source中能找到但在cleanSource中找不到，说明在注释中
                    $result .= $fullTag;
                    $offset = $endPos + 1;
                    continue;
                }

                // 尝试提取有意义的标识（如 var=... 或代码片段）
                $label = '';
                if (preg_match('/var=([\'"])([^\'"]+)\1/', $fullTag, $m)) {
                    $label = $m[2];
                } elseif (preg_match('/var=([^\s\}]+)/', $fullTag, $m2)) {
                    $label = $m2[1];
                } else {
                    // 如果没有var参数，使用eval内容的简短摘要（去除{eval 和 }）
                    $inner = trim(substr($fullTag, 5, -1)); // 去除 {eval 和 }
                    $inner = preg_replace('/\s+/', ' ', $inner);
                    $label = substr($inner, 0, 80);
                    if ($label === '') {
                        $label = 'eval';
                    }
                }

                // 将路径中的反斜杠替换为正斜杠，避免HTML注释中的"--"问题
                $safeTemplate = str_replace('\\', '/', $currentTemplate);

                // 返回带有注释的eval标签
                $result .= "<!-- START EVAL: {$label} (in: {$safeTemplate}) -->\n" .
                    $fullTag .
                    "\n<!-- END EVAL: {$label} -->";

                $offset = $endPos + 1;
            } else {
                // 如果找不到结束位置，添加当前字符并继续
                $result .= $source[$startPos];
                $offset = $startPos + 1;
            }
        }

        return $result;
    }

    /**
     * 获取当前模板文件的绝对路径
     */
    protected static function getCurrentTemplatePath($smarty)
    {
        // 默认值
        $path = 'unknown_template';

        // 方法1: 通过 Smarty 的 _source->filepath 获取（最可靠，优先）
        if (isset($smarty->_source) && isset($smarty->_source->filepath) && $smarty->_source->filepath) {
            $path = $smarty->_source->filepath;
            return $path;
        }

        // 方法2: 通过 template_resource 获取（需特殊处理 eval: 开头的情况）
        if (isset($smarty->template_resource) && $smarty->template_resource) {
            $resource = $smarty->template_resource;

            // 如果是 eval: 开头（Smarty 把内联模板标记为 eval:...），不要直接返回整个内联内容
            if (strpos($resource, 'eval:') === 0) {
                // 尝试从继承栈中寻找真实的父模板文件路径（最后一个非空 filepath）
                if (isset($smarty->_inheritance) && isset($smarty->_inheritance->sources) && !empty($smarty->_inheritance->sources)) {
                    $sources = $smarty->_inheritance->sources;
                    // 从后往前找最接近的有 filepath 的源
                    for ($i = count($sources) - 1; $i >= 0; $i--) {
                        $src = $sources[$i];
                        if (is_object($src) && isset($src->filepath) && $src->filepath) {
                            $parentPath = $src->filepath;
                            $safeParent = str_replace('\\', '/', $parentPath);
                            return 'eval (in: ' . $safeParent . ')';
                        }
                        // 有些 Smarty 版本可能把 resource 放在 resource 字段
                        if (is_object($src) && isset($src->resource) && $src->resource && strpos($src->resource, 'eval:') !== 0) {
                            return 'eval (in: ' . $src->resource . ')';
                        }
                    }
                }

                // 如果没有继承栈信息，尝试从 smarty 对象中找其他线索（兼容性尝试）
                if (isset($smarty->_current_file) && $smarty->_current_file) {
                    return 'eval (in: ' . str_replace('\\', '/', $smarty->_current_file) . ')';
                }

                // 回退：仅返回简短的 eval 标签，避免长 HTML 泄入注释
                return 'eval';
            }

            // 非 eval 的 resource，可能是相对路径，尝试解析为绝对路径
            $path = $resource;
            if (strpos($path, ':/') === false && strpos($path, DIRECTORY_SEPARATOR) !== 0) {
                $absolutePath = self::findAbsolutePath($path, $smarty);
                if ($absolutePath !== $path) {
                    $path = $absolutePath;
                }
            }

            return $path;
        }

        // 方法3: 通过继承栈获取（作为后备）
        if (
            isset($smarty->_inheritance) && isset($smarty->_inheritance->sources) &&
            !empty($smarty->_inheritance->sources)
        ) {
            $source = end($smarty->_inheritance->sources);
            if (isset($source->filepath) && $source->filepath) {
                return $source->filepath;
            }
            if (isset($source->resource) && $source->resource) {
                return $source->resource;
            }
        }

        return $path;
    }

    /**
     * 查找模板文件的绝对路径
     */
    protected static function findAbsolutePath($file, $smarty)
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

        // 5. 检查 Smarty 的模板目录（通常是主题的 templates/ 目录）
        $templateDirs = $smarty->getTemplateDir();
        if (is_array($templateDirs)) {
            foreach ($templateDirs as $dir) {
                $path = $dir . $file;
                if (file_exists($path)) {
                    return $path;
                }
            }
        } else {
            $path = $templateDirs . $file;
            if (file_exists($path)) {
                return $path;
            }
        }

        // 6. 检查父主题的模板目录
        if (_PS_PARENT_THEME_DIR_) {
            $parentPath = _PS_PARENT_THEME_DIR_ . 'templates/' . $file;
            if (file_exists($parentPath)) {
                return $parentPath;
            }
        }

        // 如果都找不到，返回原始路径
        return $file;
    }

    /**
     * 为 module: 语法查找绝对路径
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
            $moduleDir . $moduleTemplate
        ];

        foreach ($pathsToCheck as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $file; // 如果找不到，返回原始路径
    }

    /**
     * 在模块目录中查找普通路径的模板文件
     */
    protected static function findInModuleDirs($file)
    {
        // 如果文件路径已经包含 modules，尝试直接定位
        if (preg_match('#^modules/([^/]+)/(.*)$#', $file, $matches)) {
            $moduleName = $matches[1];
            $templateFile = $matches[2];

            // 先检查主题是否覆盖了该模块模板
            $themeOverride = _PS_THEME_DIR_ . 'modules/' . $moduleName . '/' . $templateFile;
            if (file_exists($themeOverride)) {
                return $themeOverride;
            }

            // 检查模块自身目录
            $moduleDir = _PS_MODULE_DIR_ . $moduleName . '/';
            $pathsToCheck = [
                $moduleDir . 'views/templates/' . $templateFile,
                $moduleDir . $templateFile
            ];

            foreach ($pathsToCheck as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    // 在SmartyDevProcessor.php文件中添加以下方法实现


    /**
     * 输出过滤器，在页面底部添加模板结构可视化工具（调试版本）
     */
    public static function addTemplateStructureViewer($output, $smarty)
    {
        // 只在开发模式下处理
        if (!Configuration::get('SMARTY_DEV_TOOLS_ENABLED')) {
            return $output;
        }

        // 检查是否为HTML文档
        if (stripos($output, '</body>') === false) {
            return $output;
        }

        // 提取模板注释信息并构建结构树
        $structureTree = self::buildTemplateStructureTree($output);

        // 生成可视化工具HTML
        $visualizerHtml = self::generateStructureVisualizer($structureTree);

        // 将可视化工具添加到页面底部
        $output = str_replace('</body>', $visualizerHtml . '</body>', $output);

        return $output;
    }

    // 在SmartyDevProcessor.php文件中更新相关方法

    /**
     * 从HTML输出中提取模板结构信息并构建结构树
     */
    protected static function buildTemplateStructureTree($html)
    {
        // 匹配所有模板结构注释，新增 START/END EVAL 支持
        preg_match_all('/<!-- (EXTENDS|START INCLUDE|END INCLUDE|START BLOCK|END BLOCK|START MODULE FETCH|END MODULE FETCH|START HOOK|END HOOK|START WIDGET|END WIDGET|START EVAL|END EVAL): (.*?) -->/', $html, $matches, PREG_SET_ORDER);

        $structure = [
            'extends' => [],
            'nodes' => [],
            'currentPath' => []
        ];

        foreach ($matches as $match) {
            $type = $match[1];
            $content = $match[2];

            switch ($type) {
                case 'EXTENDS':
                    $structure['extends'][] = $content;
                    break;

                case 'START INCLUDE':
                    $node = [
                        'type' => 'include',
                        'path' => $content,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ];

                    // 添加到当前路径的最后一个节点的children中
                    if (!empty($structure['currentPath'])) {
                        $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
                        $lastNode['children'][] = $node;
                        $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
                    } else {
                        $structure['nodes'][] = $node;
                        $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
                    }
                    break;

                case 'END INCLUDE':
                    if (!empty($structure['currentPath'])) {
                        array_pop($structure['currentPath']);
                    }
                    break;

                case 'START MODULE FETCH':
                    $node = [
                        'type' => 'module_fetch',
                        'path' => $content,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ];

                    // 添加到当前路径的最后一个节点的children中
                    if (!empty($structure['currentPath'])) {
                        $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
                        $lastNode['children'][] = $node;
                        $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
                    } else {
                        $structure['nodes'][] = $node;
                        $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
                    }
                    break;

                case 'END MODULE FETCH':
                    if (!empty($structure['currentPath'])) {
                        array_pop($structure['currentPath']);
                    }
                    break;

                case 'START BLOCK':
                    // 解析block信息: "blockName (in: templatePath)"
                    if (preg_match('/^([^\(]+) \(in: ([^\)]+)\)$/', $content, $blockMatches)) {
                        $blockName = trim($blockMatches[1]);
                        $templatePath = trim($blockMatches[2]);

                        $node = [
                            'type' => 'block',
                            'name' => $blockName,
                            'template' => $templatePath,
                            'depth' => count($structure['currentPath']),
                            'children' => []
                        ];

                        // 添加到当前路径的最后一个节点的children中
                        if (!empty($structure['currentPath'])) {
                            $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
                            $lastNode['children'][] = $node;
                            $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
                        } else {
                            $structure['nodes'][] = $node;
                            $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
                        }
                    }
                    break;

                case 'END BLOCK':
                    if (!empty($structure['currentPath'])) {
                        array_pop($structure['currentPath']);
                    }
                    break;

                case 'START HOOK':
                    // 解析hook信息: "hookName (in: templatePath)"
                    if (preg_match('/^([^\(]+) \(in: ([^\)]+)\)$/', $content, $hookMatches)) {
                        $hookName = trim($hookMatches[1]);
                        $templatePath = trim($hookMatches[2]);

                        $node = [
                            'type' => 'hook',
                            'name' => $hookName,
                            'template' => $templatePath,
                            'depth' => count($structure['currentPath']),
                            'children' => []
                        ];

                        // 添加到当前路径的最后一个节点的children中
                        if (!empty($structure['currentPath'])) {
                            $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
                            $lastNode['children'][] = $node;
                            $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
                        } else {
                            $structure['nodes'][] = $node;
                            $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
                        }
                    }
                    break;

                case 'END HOOK':
                    if (!empty($structure['currentPath'])) {
                        array_pop($structure['currentPath']);
                    }
                    break;

                case 'START WIDGET':
                    // 解析widget信息: "widgetName (in: templatePath)"
                    if (preg_match('/^([^\(]+) \(in: ([^\)]+)\)$/', $content, $widgetMatches)) {
                        $widgetName = trim($widgetMatches[1]);
                        $templatePath = trim($widgetMatches[2]);

                        $node = [
                            'type' => 'widget',
                            'name' => $widgetName,
                            'template' => $templatePath,
                            'depth' => count($structure['currentPath']),
                            'children' => []
                        ];

                        // 添加到当前路径的最后一个节点的children中
                        if (!empty($structure['currentPath'])) {
                            $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
                            $lastNode['children'][] = $node;
                            $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
                        } else {
                            $structure['nodes'][] = $node;
                            $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
                        }
                    }
                    break;

                case 'END WIDGET':
                    if (!empty($structure['currentPath'])) {
                        array_pop($structure['currentPath']);
                    }
                    break;

                case 'START EVAL':
                    // 解析eval信息: "label (in: templatePath)"
                    if (preg_match('/^([^\(]+) \\(in: ([^\\)]+)\\)$/', $content, $evalMatches)) {
                        $evalName = trim($evalMatches[1]);
                        $templatePath = trim($evalMatches[2]);

                        $node = [
                            'type' => 'eval',
                            'name' => $evalName,
                            'template' => $templatePath,
                            'depth' => count($structure['currentPath']),
                            'children' => []
                        ];

                        // 添加到当前路径的最后一个节点的children中
                        if (!empty($structure['currentPath'])) {
                            $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
                            $lastNode['children'][] = $node;
                            $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
                        } else {
                            $structure['nodes'][] = $node;
                            $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
                        }
                    }
                    break;

                case 'END EVAL':
                    if (!empty($structure['currentPath'])) {
                        array_pop($structure['currentPath']);
                    }
                    break;
            }
        }

        return $structure;
    }

    // ... existing code ...
    /**
     * 生成模板结构可视化工具的HTML
     */
    protected static function generateStructureVisualizer($structureTree)
    {
        $html = '
    <div id="smarty-structure-visualizer">
        <!-- 浮动按钮 -->
        <div id="smarty-structure-btn" title="查看模板结构">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                <path d="M8 11h8"></path>
                <path d="M8 7h6"></path>
                <path d="M8 15h4"></path>
            </svg>
        </div>
        
        <!-- 模态框 -->
        <div id="smarty-structure-modal" class="smarty-modal">
            <div class="smarty-modal-content">
                <div class="smarty-modal-header">
                    <h4>模板结构分析</h4>
                    <span class="smarty-modal-close">&times;</span>
                </div>
                <div class="smarty-modal-body">
                    <div class="smarty-tabs">
                        <button class="tablink active" data-tab="tree">结构树</button>
                        <!--<button class="tablink" data-tab="extends">Extends</button>-->
                        <button class="tablink" data-tab="includes">Includes📄</button>
                        <button class="tablink" data-tab="blocks">所有Blocks📦</button>
                        <button class="tablink" data-tab="hooks">Hooks🧷</button>
                        <button class="tablink" data-tab="widgets">Widgets⚙️</button>
                        <button class="tablink" data-tab="fetches">模块Fetch📌</button>
                        <button class="tablink" data-tab="evals">Evals📊</button>
                    </div>
                    
                    <div id="tree" class="tabcontent active">
                        <h3>模板结构树</h3>
                        ' . self::renderStructureTree($structureTree) . '
                    </div>
                    
                    <!--<div id="extends" class="tabcontent">
                        <h3>模板继承关系</h3>
                        ' . self::renderExtends($structureTree['extends']) . '
                    </div>-->
                    
                    <div id="includes" class="tabcontent">
                        <h3>包含的模板文件</h3>
                        ' . self::renderIncludes($structureTree) . '
                    </div>
                    
                    <div id="blocks" class="tabcontent">
                        <h3>所有块定义</h3>
                        ' . self::renderAllBlocks($structureTree) . '
                    </div>
                    
                    <div id="hooks" class="tabcontent">
                        <h3>所有Hooks</h3>
                        ' . self::renderAllHooks($structureTree) . '
                    </div>
                    
                    <div id="widgets" class="tabcontent">
                        <h3>所有Widgets</h3>
                        ' . self::renderAllWidgets($structureTree) . '
                    </div>
                    
                    <div id="fetches" class="tabcontent">
                        <h3>模块Fetch调用</h3>
                        ' . self::renderModuleFetches($structureTree) . '
                    </div>

                    <div id="evals" class="tabcontent">
                        <h3>所有Eval调用</h3>
                        ' . self::renderAllEvals($structureTree) . '
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    #smarty-structure-visualizer {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 14px;
    }
    
    #smarty-structure-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        background-color: #25b9d7;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10000;
        transition: all 0.3s ease;
    }
    
    #smarty-structure-btn:hover {
        background-color: #1e9fbb;
        transform: scale(1.05);
    }
    
    .smarty-modal {
        display: none;
        position: fixed;
        z-index: 10001;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    
    .smarty-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border-radius: 8px;
        width: 80%;
        max-width: 900px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        max-height: 80vh;
        display: flex;
        flex-direction: column;
    }
    
    .smarty-modal-header {
        padding: 10px 20px;
        background-color: #25b9d7;
        color: white;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .smarty-modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
    }
    
    .smarty-modal-close {
        color: white;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .smarty-modal-close:hover {
        color: #eee;
    }
    
    .smarty-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .smarty-tabs {
        display: flex;
        border-bottom: 1px solid #ddd;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .tablink {
        background-color: inherit;
        border: none;
        padding: 10px 20px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 16px;
        color: #555;
        white-space: nowrap;
    }
    
    .tablink:hover {
        background-color: #f1f1f1;
    }
    
    .tablink.active {
        color: #25b9d7;
        border-bottom: 3px solid #25b9d7;
    }
    
    .tabcontent {
        display: none;
    }
    
    .tabcontent.active {
        display: block;
    }
    
    .template-item {
        padding: 8px 12px;
        margin: 5px 0;
        background-color: #f9f9f9;
        border-left: 4px solid #25b9d7;
        border-radius: 4px;
        word-break: break-all;
    }
    
    .block-item {
        padding: 10px;
        margin: 8px 0;
        background-color: #f5f5f5;
        border-radius: 4px;
        border-left: 4px solid #32c682;
        word-break: break-all;
    }
    
    .hook-item, .widget-item {
        padding: 10px;
        margin: 8px 0;
        background-color: #e3f2fd;
        border-radius: 4px;
        border-left: 4px solid #2196f3;
        word-break: break-all;
    }
    
    .fetch-item {
        padding: 10px;
        margin: 8px 0;
        background-color: #fff8e1;
        border-radius: 4px;
        border-left: 4px solid #ffc107;
        word-break: break-all;
    }

    .eval-item {
        padding: 10px;
        margin: 8px 0;
        background-color: #f3e5f5;
        border-radius: 4px;
        border-left: 4px solid #9c27b0;
        word-break: break-all;
    }
    
    .structure-tree {
        line-height: 1.6;
    }
    
    .structure-tree ul {
        list-style-type: none;
        padding-left: 20px;
        margin: 0;
    }
    
    .structure-tree li {
        position: relative;
        padding: 5px 0;
        padding-left: 20px;
    }
    
    .structure-tree li:before {
        content: "";
        position: absolute;
        top: 12px;
        left: 0;
        width: 12px;
        height: 1px;
        background: #ccc;
    }
    
    .structure-tree li:after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 1px;
        background: #ccc;
    }
    
    .structure-tree li:last-child:after {
        height: 12px;
    }
    
    .structure-tree .node-icon {
        margin-right: 6px;
        color: #888;
    }
    
    .structure-tree .block-node {
        color: #2c5282;
        font-weight: 500;
    }
    
    .structure-tree .include-node {
        color: #2d3748;
    }
    
    .structure-tree .hook-node {
        color: #0d47a1;
        font-weight: 500;
    }
    
    .structure-tree .widget-node {
        color: #4a148c;
        font-weight: 500;
    }

    .structure-tree .eval-node {
        color: #6a1b9a;
        font-weight: 500;
    }
    
    .structure-tree .fetch-node {
        color: #8a6d3b;
        font-weight: 500;
    }
    
    .structure-tree .node-details {
        font-size: 0.85em;
        color: #718096;
        margin-left: 8px;
    }
    
    .structure-tree .toggle-icon {
        cursor: pointer;
        margin-right: 4px;
        color: #a0aec0;
        user-select: none;
    }
    
    .structure-tree .collapsed .children {
        display: none;
    }
    
    /* 移动端适配 */
    @media screen and (max-width: 768px) {
        .smarty-modal-content {
            width: 95%;
            margin: 2% auto;
            max-height: 95vh;
        }
        
        .smarty-modal-header {
            padding: 12px 15px;
        }
        
        .smarty-modal-header h2 {
            font-size: 1.2rem;
        }
        
        .smarty-modal-body {
            padding: 15px;
        }
        
        .smarty-tabs {
            flex-wrap: wrap;
        }
        
        .tablink {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .structure-tree ul {
            padding-left: 15px;
        }
        
        .structure-tree li {
            padding-left: 15px;
        }
    }
    
    @media screen and (max-width: 480px) {
        #smarty-structure-btn {
            width: 40px;
            height: 40px;
            bottom: 10px;
            right: 10px;
        }
        
        .smarty-modal-content {
            width: 98%;
            margin: 1% auto;
            border-radius: 4px;
        }
        
        .smarty-modal-header {
            padding: 10px 12px;
        }
        
        .smarty-modal-header h2 {
            font-size: 1.1rem;
        }
        
        .smarty-modal-close {
            font-size: 24px;
        }
        
        .smarty-modal-body {
            padding: 10px;
        }
        
        .tablink {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .template-item,
        .block-item,
        .hook-item,
        .widget-item {
            padding: 6px 8px;
        }
        
        .structure-tree .node-details {
            display: block;
            margin-left: 0;
            margin-top: 4px;
        }
    }
    </style>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // 获取元素
        var btn = document.getElementById("smarty-structure-btn");
        var modal = document.getElementById("smarty-structure-modal");
        var closeBtn = document.querySelector(".smarty-modal-close");
        var tablinks = document.querySelectorAll(".tablink");
        var tabcontents = document.querySelectorAll(".tabcontent");
        
        // 打开模态框
        btn.addEventListener("click", function() {
            modal.style.display = "block";
            // 防止背景滚动
            document.body.style.overflow = "hidden";
        });
        
        // 关闭模态框
        closeBtn.addEventListener("click", function() {
            modal.style.display = "none";
            // 恢复背景滚动
            document.body.style.overflow = "auto";
        });
        
        // 点击外部关闭模态框
        window.addEventListener("click", function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
                // 恢复背景滚动
                document.body.style.overflow = "auto";
            }
        });
        
        // 标签切换
        tablinks.forEach(function(tablink) {
            tablink.addEventListener("click", function() {
                var tabName = this.getAttribute("data-tab");
                
                // 移除所有active类
                tablinks.forEach(function(tl) {
                    tl.classList.remove("active");
                });
                tabcontents.forEach(function(tc) {
                    tc.classList.remove("active");
                });
                
                // 添加active类到当前标签
                this.classList.add("active");
                document.getElementById(tabName).classList.add("active");
            });
        });
        
        // 树形结构折叠/展开功能
        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-icon")) {
                var parentLi = e.target.closest("li");
                if (parentLi) {
                    parentLi.classList.toggle("collapsed");
                    e.target.textContent = parentLi.classList.contains("collapsed") ? "⊕" : "⊖";
                }
            }
        });
        
        // 监听窗口大小变化，确保模态框在窗口大小变化时保持居中
        window.addEventListener("resize", function() {
            if (modal.style.display === "block") {
                modal.style.display = "flex";
                modal.style.alignItems = "center";
            }
        });
    });
    </script>
    ';

        return $html;
    }

    /**
     * 渲染结构树
     */
    protected static function renderStructureTree($structureTree)
    {
        $html = '<div class="structure-tree"><ul>';

        // 渲染extends信息
        if (!empty($structureTree['extends'])) {
            $html .= '<li>';
            $html .= '<span class="toggle-icon">⊖</span>';
            $html .= '<span class="node-icon">📁</span>';
            $html .= '<span class="block-node">Extends</span>';
            $html .= '<div class="children">';
            foreach ($structureTree['extends'] as $extend) {
                $html .= '<li>';
                $html .= '<span class="node-icon">↳</span>';
                $html .= htmlspecialchars($extend);
                $html .= '</li>';
            }
            $html .= '</div></li>';
        }

        // 渲染节点
        foreach ($structureTree['nodes'] as $node) {
            $html .= self::renderStructureNode($node);
        }

        $html .= '</ul></div>';

        return $html;
    }

    /**
     * 渲染结构节点
     */
    protected static function renderStructureNode($node)
    {
        $hasChildren = !empty($node['children']);

        $html = '<li>';

        if ($hasChildren) {
            $html .= '<span class="toggle-icon">⊖</span>';
        } else {
            $html .= '<span class="toggle-icon" style="visibility: hidden;">⊕</span>';
        }

        switch ($node['type']) {
            case 'block':
                $html .= '<span class="node-icon">📦</span>';
                $html .= '<span class="block-node">' . htmlspecialchars($node['name']) . '</span>';
                $html .= '<span class="node-details">(in: ' . htmlspecialchars($node['template']) . ')</span>';
                break;

            case 'include':
                $html .= '<span class="node-icon">📄</span>';
                $html .= '<span class="include-node">' . htmlspecialchars(basename($node['path'])) . '</span>';
                $html .= '<span class="node-details">(' . htmlspecialchars($node['path']) . ')</span>';
                break;

            case 'module_fetch':
                $html .= '<span class="node-icon">📌</span>';
                $html .= '<span class="fetch-node">' . htmlspecialchars(basename($node['path'])) . '</span>';
                $html .= '<span class="node-details">(' . htmlspecialchars($node['path']) . ')</span>';
                break;

            case 'hook':
                $html .= '<span class="node-icon">🧷</span>';
                $html .= '<span class="hook-node">' . htmlspecialchars($node['name']) . '</span>';
                $html .= '<span class="node-details">(in: ' . htmlspecialchars($node['template']) . ')</span>';
                break;

            case 'widget':
                $html .= '<span class="node-icon">⚙️</span>';
                $html .= '<span class="widget-node">' . htmlspecialchars($node['name']) . '</span>';
                $html .= '<span class="node-details">(in: ' . htmlspecialchars($node['template']) . ')</span>';
                break;

            case 'eval':
                $html .= '<span class="node-icon">📊</span>';
                $html .= '<span class="eval-node">' . htmlspecialchars($node['name']) . '</span>';
                $html .= '<span class="node-details">(in: ' . htmlspecialchars($node['template']) . ')</span>';
                break;
        }

        if ($hasChildren) {
            $html .= '<div class="children"><ul>';
            foreach ($node['children'] as $child) {
                $html .= self::renderStructureNode($child);
            }
            $html .= '</ul></div>';
        }

        $html .= '</li>';

        return $html;
    }

    /**
     * 渲染Extends信息
     */
    protected static function renderExtends($extends)
    {
        if (empty($extends)) {
            return '<p>没有找到extends关系</p>';
        }

        $html = '';
        foreach ($extends as $extend) {
            $html .= '<div class="template-item">' . htmlspecialchars($extend) . '</div>';
        }

        return $html;
    }

    /**
     * 渲染Includes信息
     */
    protected static function renderIncludes($structureTree)
    {
        // 收集所有include节点
        $includes = [];
        self::collectNodesByType($structureTree, 'include', $includes);

        if (empty($includes)) {
            return '<p>没有找到include文件</p>';
        }

        $html = '';
        foreach ($includes as $include) {
            $html .= '<div class="template-item">' . htmlspecialchars($include['path']) . '</div>';
        }

        return $html;
    }

    /**
     * 渲染所有Blocks信息
     */
    protected static function renderAllBlocks($structureTree)
    {
        // 收集所有block节点
        $blocks = [];
        self::collectNodesByType($structureTree, 'block', $blocks);

        if (empty($blocks)) {
            return '<p>没有找到block定义</p>';
        }

        $html = '';
        foreach ($blocks as $block) {
            $html .= '<div class="block-item">';
            $html .= '<strong>' . htmlspecialchars($block['name']) . '</strong>';
            $html .= '<div>所在模板: ' . htmlspecialchars($block['template']) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * 渲染模块Fetch信息
     */
    protected static function renderModuleFetches($structureTree)
    {
        // 收集所有module_fetch节点
        $fetches = [];
        self::collectNodesByType($structureTree, 'module_fetch', $fetches);

        if (empty($fetches)) {
            return '<p>没有找到模块fetch调用</p>';
        }

        $html = '';
        foreach ($fetches as $fetch) {
            $html .= '<div class="fetch-item">' . htmlspecialchars($fetch['path']) . '</div>';
        }

        return $html;
    }

    /**
     * 递归收集指定类型的节点
     */
    protected static function collectNodesByType($node, $type, &$result)
    {
        if (isset($node['type']) && $node['type'] === $type) {
            $result[] = $node;
        }

        if (isset($node['nodes'])) {
            foreach ($node['nodes'] as $childNode) {
                self::collectNodesByType($childNode, $type, $result);
            }
        }

        if (isset($node['children'])) {
            foreach ($node['children'] as $childNode) {
                self::collectNodesByType($childNode, $type, $result);
            }
        }
    }

      /**
     * 渲染所有Hooks信息
     */
    protected static function renderAllHooks($structureTree)
    {
        // 收集所有hook节点
        $hooks = [];
        self::collectNodesByType($structureTree, 'hook', $hooks);

        if (empty($hooks)) {
            return '<p>没有找到hook调用</p>';
        }

        $html = '';
        foreach ($hooks as $hook) {
            $html .= '<div class="hook-item">';
            $html .= '<strong>' . htmlspecialchars($hook['name']) . '</strong>';
            $html .= '<div>所在模板: ' . htmlspecialchars($hook['template']) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * 渲染所有Widgets信息
     */
    protected static function renderAllWidgets($structureTree)
    {
        // 收集所有widget节点
        $widgets = [];
        self::collectNodesByType($structureTree, 'widget', $widgets);

        if (empty($widgets)) {
            return '<p>没有找到widget调用</p>';
        }

        $html = '';
        foreach ($widgets as $widget) {
            $html .= '<div class="widget-item">';
            $html .= '<strong>' . htmlspecialchars($widget['name']) . '</strong>';
            $html .= '<div>所在模板: ' . htmlspecialchars($widget['template']) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * 渲染所有Evals信息（新增）
     */
    protected static function renderAllEvals($structureTree)
    {
        // 收集所有eval节点
        $evals = [];
        self::collectNodesByType($structureTree, 'eval', $evals);

        if (empty($evals)) {
            return '<p>没有找到eval调用</p>';
        }

        $html = '';
        foreach ($evals as $eval) {
            $html .= '<div class="eval-item">';
            $html .= '<strong>' . htmlspecialchars($eval['name']) . '</strong>';
            $html .= '<div>所在模板: ' . htmlspecialchars($eval['template']) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
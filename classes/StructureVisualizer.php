<?php

/**
 * Smarty 模板结构可视化工具
 *
 * 负责解析 HTML 输出中的模板注释信息,构建可视化的结构树,
 * 并生成用户友好的调试界面。
 *
 * @author    Smarty Dev Tools
 * @copyright 2023 Smarty Dev Tools
 * @license   Academic Free License (AFL 3.0)
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StructureVisualizer
{
    /**
     * 输出过滤器，在页面底部添加模板结构可视化工具
     *
     * @param string $output HTML 输出
     * @param Smarty $smarty Smarty 实例
     * @return string 修改后的 HTML 输出
     */
    public static function addTemplateStructureViewer($output, $smarty)
    {
        try {
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

            // 从全局变量中获取继承链信息(由SimpleTagProcessor在编译时保存)
            if (isset($GLOBALS['smarty_extends_chain']) && !empty($GLOBALS['smarty_extends_chain'])) {
                $structureTree['extends'] = $GLOBALS['smarty_extends_chain'];
                // 清空全局变量
                unset($GLOBALS['smarty_extends_chain']);
            }

            // 调试日志 - 记录到专用文件
            if (_PS_MODE_DEV_) {
                $logFile = _PS_ROOT_DIR_ . '/var/logs/smarty_dev_tools.log';
                $logContent = str_repeat('=', 80) . "\n";
                $logContent .= date('[Y-m-d H:i:s] ') . "Structure Tree\n";
                $logContent .= str_repeat('-', 80) . "\n";
                $logContent .= json_encode(
                    $structureTree,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . "\n";
                $logContent .= str_repeat('=', 80) . "\n\n";
                file_put_contents($logFile, $logContent, FILE_APPEND);
            }

            // 生成各部分HTML
            $structure_tree_html = self::renderStructureTree($structureTree);
            // 不再需要单独的 extends_html
            // $extends_html = self::renderExtends($structureTree['extends']);
            // 其他Tab暂时不需要收集渲染
            // $includes_html = self::renderIncludes($structureTree);
            // $blocks_html = self::renderAllBlocks($structureTree);
            // $hooks_html = self::renderAllHooks($structureTree);
            // $widgets_html = self::renderAllWidgets($structureTree);
            // $fetches_html = self::renderModuleFetches($structureTree);
            // $evals_html = self::renderAllEvals($structureTree);

            // 渲染Smarty视图文件
            $tplPath = _PS_MODULE_DIR_ . 'zh_smartydevtools/views/templates/admin/structure_viewer.tpl';
            $moduleDir = _MODULE_DIR_ . 'zh_smartydevtools/';

            $viewerSmarty = clone $smarty;
            $viewerSmarty->assign([
                'structure_tree_html' => $structure_tree_html,
                // 不再需要 extends_html
                // 'extends_html' => $extends_html,
                // 其他Tab暂时不传递
                // 'includes_html' => $includes_html,
                // 'blocks_html' => $blocks_html,
                // 'hooks_html' => $hooks_html,
                // 'widgets_html' => $widgets_html,
                // 'fetches_html' => $fetches_html,
                // 'evals_html' => $evals_html,
                'module_dir' => $moduleDir,
            ]);

            $visualizerHtml = $viewerSmarty->fetch($tplPath);

            // 将可视化工具添加到页面底部
            $output = str_replace('</body>', $visualizerHtml . '</body>', $output);

            return $output;
        } catch (Exception $e) {
            // 静默失败,记录错误日志但不影响页面渲染
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog(
                    'Smarty Dev Tools Error: ' . $e->getMessage(),
                    3,
                    null,
                    null,
                    null,
                    true
                );
            }
            return $output;
        }
    }
    /**
     * 从HTML输出中提取模板结构信息并构建结构树
     *
     * 支持的注释标记:
     * - EXTENDS: 模板继承
     * - START/END INCLUDE: 模板包含
     * - START/END BLOCK: 块定义
     * - START/END MODULE FETCH: 模块获取
     * - START/END HOOK: 钩子调用
     * - START/END WIDGET: 组件调用
     * - START/END EVAL: 动态求值
     *
     * @param string $html HTML 输出内容
     * @return array 结构树数组
     */
    protected static function buildTemplateStructureTree($html)
    {
        // 匹配所有模板结构注释 (包括单行和多行格式)
        preg_match_all(
            '/<!-- (EXTENDS|START INCLUDE|END INCLUDE|START BLOCK|END BLOCK|START MODULE FETCH|END MODULE FETCH|START HOOK|END HOOK|START WIDGET|END WIDGET|START EVAL|END EVAL):\s*(.*?)\s*-->/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

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
                    // 解析 extends 信息: "parentTemplate (from: currentTemplate)"
                    if (preg_match('/^(.+?) \(from: (.+?)\)$/', $content, $extendsMatches)) {
                        $structure['extends'][] = [
                            'parent' => trim($extendsMatches[1]),
                            'child' => trim($extendsMatches[2]),
                        ];
                    } else {
                        // 兼容旧格式(只有父模板名称)
                        $structure['extends'][] = [
                            'parent' => trim($content),
                            'child' => '(unknown)',
                        ];
                    }
                    break;

                case 'START INCLUDE':
                    self::addNodeToStructure($structure, [
                        'type' => 'include',
                        'path' => $content,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ]);
                    break;

                case 'END INCLUDE':
                    self::popCurrentPath($structure);
                    break;

                case 'START MODULE FETCH':
                    self::addNodeToStructure($structure, [
                        'type' => 'module_fetch',
                        'path' => $content,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ]);
                    break;

                case 'END MODULE FETCH':
                    self::popCurrentPath($structure);
                    break;

                case 'START BLOCK':
                    // 解析block信息: "blockName (in: templatePath)"
                    if (preg_match('/^([^\(]+) \(in: ([^\)]+)\)$/', $content, $blockMatches)) {
                        $blockName = trim($blockMatches[1]);
                        $templatePath = trim($blockMatches[2]);

                        self::addNodeToStructure($structure, [
                            'type' => 'block',
                            'name' => $blockName,
                            'template' => $templatePath,
                            'depth' => count($structure['currentPath']),
                            'children' => []
                        ]);
                    }
                    break;

                case 'END BLOCK':
                    self::popCurrentPath($structure);
                    break;

                case 'START HOOK':
                    // 解析hook信息: "hookName (in: templatePath)" 或 "hookName"
                    $content = trim($content);

                    if (preg_match('/^([^\(]+)\s*\(in:\s*([^\)]+)\)$/s', $content, $hookMatches)) {
                        // 格式: hookName (in: templatePath)
                        $hookName = trim($hookMatches[1]);
                        $templatePath = trim($hookMatches[2]);
                    } else {
                        // 格式: 只有 hookName (某些运行时生成的 hook)
                        $hookName = $content;
                        $templatePath = '(runtime)';
                    }

                    self::addNodeToStructure($structure, [
                        'type' => 'hook',
                        'name' => $hookName,
                        'template' => $templatePath,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ]);
                    break;

                case 'END HOOK':
                    self::popCurrentPath($structure);
                    break;

                case 'START WIDGET':
                    // 解析widget信息: "widgetName (in: templatePath)" 或 "widgetName"
                    $content = trim($content);

                    if (preg_match('/^([^\(]+)\s*\(in:\s*([^\)]+)\)$/s', $content, $widgetMatches)) {
                        // 格式: widgetName (in: templatePath)
                        $widgetName = trim($widgetMatches[1]);
                        $templatePath = trim($widgetMatches[2]);
                    } else {
                        // 格式: 只有 widgetName
                        $widgetName = $content;
                        $templatePath = '(runtime)';
                    }

                    self::addNodeToStructure($structure, [
                        'type' => 'widget',
                        'name' => $widgetName,
                        'template' => $templatePath,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ]);
                    break;

                case 'END WIDGET':
                    self::popCurrentPath($structure);
                    break;

                case 'START EVAL':
                    // 解析eval信息: "label (in: templatePath)" 或 "label"
                    $content = trim($content);

                    if (preg_match('/^([^\(]+)\s*\(in:\s*([^\)]+)\)$/s', $content, $evalMatches)) {
                        // 格式: label (in: templatePath)
                        $evalName = trim($evalMatches[1]);
                        $templatePath = trim($evalMatches[2]);
                    } else {
                        // 格式: 只有 label
                        $evalName = $content;
                        $templatePath = '(runtime)';
                    }

                    self::addNodeToStructure($structure, [
                        'type' => 'eval',
                        'name' => $evalName,
                        'template' => $templatePath,
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ]);
                    break;

                case 'END EVAL':
                    self::popCurrentPath($structure);
                    break;
            }
        }

        return $structure;
    }

    /**
     * 添加节点到结构树
     *
     * @param array $structure 结构树引用
     * @param array $node 节点数据
     */
    protected static function addNodeToStructure(&$structure, $node)
    {
        if (!empty($structure['currentPath'])) {
            $lastNode = &$structure['currentPath'][count($structure['currentPath']) - 1];
            $lastNode['children'][] = $node;
            $structure['currentPath'][] = &$lastNode['children'][count($lastNode['children']) - 1];
        } else {
            $structure['nodes'][] = $node;
            $structure['currentPath'][] = &$structure['nodes'][count($structure['nodes']) - 1];
        }
    }

    /**
     * 从当前路径栈中弹出最后一个节点
     *
     * @param array $structure 结构树引用
     */
    protected static function popCurrentPath(&$structure)
    {
        if (!empty($structure['currentPath'])) {
            array_pop($structure['currentPath']);
        }
    }

    /**
     * 渲染结构树HTML
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderStructureTree($structureTree)
    {
        $html = '<div class="structure-tree"><ul>';

        // 渲染extends信息 - 使用树形层级结构
        if (!empty($structureTree['extends'])) {
            // 构建继承链: child -> parent -> grandparent
            $childToParent = [];
            foreach ($structureTree['extends'] as $extend) {
                if (is_array($extend) && isset($extend['parent'])) {
                    $childToParent[$extend['child']] = $extend['parent'];
                }
            }

            // 找到最底层的子模板(当前页面)
            $allChildren = array_keys($childToParent);
            $allParents = array_values($childToParent);
            $rootChildren = array_diff($allChildren, $allParents);

            if (!empty($rootChildren)) {
                $html .= '<li>';
                $html .= '<span class="toggle-icon">⊖</span>';
                $html .= '<span class="node-icon">📁</span>';
                $html .= '<span>Extends</span>';
                $html .= '<ul class="children">';

                // 递归渲染继承链
                foreach ($rootChildren as $child) {
                    $html .= self::renderExtendsChain($child, $childToParent, 0);
                }

                $html .= '</ul></li>';
            }
        }

        // 渲染节点
        foreach ($structureTree['nodes'] as $node) {
            $html .= self::renderStructureNode($node);
        }

        $html .= '</ul></div>';

        return $html;
    }

    /**
     * 递归渲染继承链 - 树形层级结构
     *
     * @param string $template 当前模板路径
     * @param array $childToParent 子模板到父模板的映射
     * @param int $depth 当前深度
     * @return string HTML 字符串
     */
    protected static function renderExtendsChain($template, $childToParent, $depth = 0)
    {
        $html = '<li>';

        // 根据深度添加图标
        if ($depth === 0) {
            $html .= '<span class="node-icon">📄</span>';
            $html .= '<span class="template-node">' . htmlspecialchars(basename($template)) . '</span>';
        } else {
            $html .= '<span class="node-icon">↳</span>';
            $html .= '<span class="extends-node">' . htmlspecialchars($template) . '</span>';
        }

        // 如果有父模板,递归渲染
        if (isset($childToParent[$template])) {
            $html .= '<ul class="children">';
            $html .= self::renderExtendsChain($childToParent[$template], $childToParent, $depth + 1);
            $html .= '</ul>';
        }

        $html .= '</li>';

        return $html;
    }

    /**
     * 渲染单个结构节点
     *
     * @param array $node 节点数据
     * @return string HTML 字符串
     */
    protected static function renderStructureNode($node)
    {
        $hasChildren = !empty($node['children']);

        $html = '<li>';

        // 折叠/展开图标
        if ($hasChildren) {
            $html .= '<span class="toggle-icon">⊖</span>';
        } else {
            $html .= '<span class="toggle-icon" style="visibility: hidden;">⊕</span>';
        }

        // 根据节点类型渲染不同的内容
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

        // 渲染子节点
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
     * 渲染Extends信息 - 显示完整继承链
     *
     * @param array $extends Extends 数组 [['parent' => '...', 'child' => '...'], ...]
     * @return string HTML 字符串
     */
    protected static function renderExtends($extends)
    {
        if (empty($extends)) {
            return '<p>没有找到extends关系</p>';
        }

        // 构建继承链(从最底层子模板到最顶层父模板)
        $chain = [];
        $childToParent = [];

        // 建立子模板到父模板的映射
        foreach ($extends as $extend) {
            $childToParent[$extend['child']] = $extend['parent'];
        }

        // 找到继承链的起点(最底层的子模板 - 当前访问的页面)
        $allChildren = array_keys($childToParent);
        $allParents = array_values($childToParent);
        $startTemplates = array_diff($allChildren, $allParents);

        if (empty($startTemplates)) {
            // 如果找不到明确的起点,使用第一个extends关系
            $startTemplate = $extends[0]['child'];
        } else {
            $startTemplate = reset($startTemplates);
        }

        // 构建继承链
        $current = $startTemplate;
        $chain[] = $current;
        $visited = [$current => true];

        while (isset($childToParent[$current])) {
            $parent = $childToParent[$current];

            // 防止循环引用
            if (isset($visited[$parent])) {
                break;
            }

            $chain[] = $parent;
            $visited[$parent] = true;
            $current = $parent;
        }

        // 渲染继承链 - 从最底层到最顶层
        $html = '<div class="extends-chain">';
        $html .= '<div class="chain-title">模板继承链 (从当前页面到最终父模板):</div>';
        $html .= '<div class="chain-list">';

        foreach ($chain as $index => $template) {
            $isFirst = ($index === 0);
            $isLast = ($index === count($chain) - 1);

            $html .= '<div class="chain-item">';

            // 显示层级序号
            $html .= '<span class="chain-level">Level ' . ($index + 1) . '</span>';

            // 显示箭头(除了第一项)
            if (!$isFirst) {
                $html .= '<span class="chain-arrow">↑ extends</span>';
            }

            // 显示模板路径
            $html .= '<div class="chain-template">';
            if ($isFirst) {
                $html .= '<strong style="color: #2196F3;">📄 ' . htmlspecialchars($template) . '</strong>';
                $html .= '<span class="chain-label">(当前页面)</span>';
            } elseif ($isLast) {
                $html .= '<strong style="color: #4CAF50;">📋 ' . htmlspecialchars($template) . '</strong>';
                $html .= '<span class="chain-label">(最终父模板)</span>';
            } else {
                $html .= '<span style="color: #FF9800;">📑 ' . htmlspecialchars($template) . '</span>';
                $html .= '<span class="chain-label">(中间层)</span>';
            }
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';

        // 添加统计信息
        $html .= '<div class="chain-stats">';
        $html .= '总计 ' . count($chain) . ' 层继承';
        $html .= '</div>';

        $html .= '</div>';

        // 添加样式
        $html .= '<style>
.extends-chain {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}
.chain-title {
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 15px;
    color: #333;
}
.chain-list {
    border-left: 3px solid #2196F3;
    padding-left: 20px;
}
.chain-item {
    margin-bottom: 15px;
    position: relative;
}
.chain-level {
    display: inline-block;
    background: #E3F2FD;
    color: #1976D2;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-bottom: 5px;
}
.chain-arrow {
    display: block;
    color: #9E9E9E;
    font-size: 12px;
    margin: 5px 0;
    padding-left: 10px;
}
.chain-template {
    background: #F5F5F5;
    padding: 10px;
    border-radius: 4px;
    font-family: "Courier New", monospace;
    font-size: 12px;
    word-break: break-all;
}
.chain-label {
    display: inline-block;
    margin-left: 10px;
    color: #666;
    font-size: 11px;
    font-style: italic;
}
.chain-stats {
    margin-top: 15px;
    padding: 8px;
    background: #E8F5E9;
    border-radius: 4px;
    font-size: 12px;
    color: #2E7D32;
    text-align: center;
}
</style>';

        return $html;
    }

    /**
     * 渲染Includes信息
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderIncludes($structureTree)
    {
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
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderAllBlocks($structureTree)
    {
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
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderModuleFetches($structureTree)
    {
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
     * 渲染所有Hooks信息
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderAllHooks($structureTree)
    {
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
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderAllWidgets($structureTree)
    {
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
     * 渲染所有Evals信息
     *
     * @param array $structureTree 结构树
     * @return string HTML 字符串
     */
    protected static function renderAllEvals($structureTree)
    {
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

    /**
     * 递归收集指定类型的节点
     *
     * @param array $node 节点或结构树
     * @param string $type 节点类型
     * @param array $result 收集结果数组引用
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
}

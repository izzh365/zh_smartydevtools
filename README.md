# Smarty Dev Tools - PrestaShop 开发调试工具

[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7+-blue.svg)](https://www.prestashop.com/)
[![Smarty](https://img.shields.io/badge/Smarty-4.3+-green.svg)](https://www.smarty.net/)
[![License](https://img.shields.io/badge/License-AFL%203.0-yellow.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Version-2.0.0-orange.svg)](CHANGELOG.md)

> 为 PrestaShop 模板开发者提供的终极调试工具,可视化展示 Smarty 模板结构、继承链、包含关系和钩子调用。

---

## 🎉 v2.0.0 新特性

### 🔒 浏览器级别隔离设计

**核心理念**: 每个开发人员在自己的浏览器独立控制开关,互不影响!

#### 配置存储策略

| 配置项 | 存储位置 | 作用域 | 说明 |
|--------|----------|--------|------|
| `SMARTY_DEV_TOOLS_ENABLED` | **数据库** | 全局(所有用户) | 总开关,所有人保持一致 |
| `SMARTY_SHOW_COMMENTS` | **Cookie 仅** | 单浏览器 | 只影响设置了此 Cookie 的浏览器 |
| `SMARTY_SHOW_VIEWER` | **Cookie 仅** | 单浏览器 | 只影响设置了此 Cookie 的浏览器 |

#### 工作流程示例

```
场景: 3 个开发人员 A、B、C 的不同使用方式

1. A 只需要查看 HTML 注释
   → 开启 Element Comments
   → Structure Tree Viewer 变为可用,但 A 选择不开启
   → A 的浏览器: 只看到 HTML 注释,无浮动按钮

2. B 需要完整的调试功能
   → 先开启 Element Comments (必须)
   → 再开启 Structure Tree Viewer (此时才可用)
   → B 的浏览器: 既有 HTML 注释,也有结构树按钮

3. C 不需要任何调试功能
   → 保持所有开关关闭
   → C 的浏览器: 纯净的前台页面,无任何调试信息

4. 测试完成,快速清理
   → A、B、C 都关闭总开关
   → 所有 Cookie 自动删除,后台显示全部关闭

结果: 3 个人互不影响,各自使用自己需要的功能!
```

### 🔄 智能依赖关系

**前置条件设计**: Element Comments 是 Structure Tree Viewer 的必要前提

- **Element Comments 关闭** → Structure Tree Viewer 自动关闭并禁用 (无法开启)
- **Element Comments 开启** → Structure Tree Viewer 变为可用 (用户可自由选择)
- **关闭总开关** → 两个子功能同时关闭并禁用,所有 Cookie 自动删除
- **开启总开关** → Element Comments 可用,但 Structure Tree Viewer 仍需等待 Element Comments 开启

**设计理念**: 结构树基于 HTML 注释构建,因此必须先启用注释功能才能显示结构树。这种单向依赖关系确保了功能的逻辑一致性,同时保留了用户的灵活性(可以只用注释不用结构树)。

### 🎨 现代化的 Toggle Switch 界面

- **AJAX 即时生效**: 无需点击 Save 按钮,切换即生效
- **实时状态同步**: 前后端状态完全同步,UI 准确反映 Cookie 状态
- **智能联动**: 开关之间自动联动,无需手动调整
- **防重复提交**: 内置请求锁,避免快速点击导致状态混乱
- **视觉反馈**: 圆形滑块设计,开/关状态一目了然

### 🍪 Cookie 生命周期

- **有效期**: 8 小时 (28800 秒)
- **路径**: `/` (全站有效)
- **HttpOnly**: `true` (防止 JavaScript 访问)
- **自动过期**: 无需手动清理
- **总开关关闭**: 自动删除所有 Cookie

---

## 📋 目录

- [功能特性](#-功能特性)
- [系统要求](#-系统要求)
- [安装指南](#-安装指南)
- [快速入门](#-快速入门)
- [使用指南](#-使用指南)
- [扩展开发](#-扩展开发)
- [故障排查](#-故障排查)
- [性能优化](#-性能优化)
- [常见问题](#-常见问题)
- [贡献指南](#-贡献指南)

---

## ✨ 功能特性

### 核心功能

- 🌲 **模板结构树** - 可视化展示完整的模板层级结构
- 🔗 **继承链追踪** - 追踪 `{extends}` 继承关系,从子模板到顶层父模板
- 📦 **Block 可视化** - 显示所有 `{block}` 定义及其所在模板
- 🔌 **Hook 监控** - 追踪所有 `{hook}` 调用点及注册的模块
- 🧩 **Widget 分析** - 显示 `{widget}` 组件的使用情况
- 📄 **Include 追踪** - 显示所有 `{include}` 文件及嵌套关系
- ⚡ **Eval 警告** - 标记运行时动态内容,提示潜在问题
- 🎨 **响应式界面** - 支持 PC 和移动端,浮动按钮一键唤起

### 技术特性

- ✅ **零侵入** - 仅在开发模式激活,生产环境零影响
- ✅ **模块化架构** - Factory + Strategy 模式,易于扩展
- ✅ **智能路径解析** - 自动识别主题覆盖、模块模板、继承栈
- ✅ **注释清理** - 自动过滤 Smarty `{* *}` 和 HTML `<!-- -->` 注释
- ✅ **性能优化** - 工具方法复用,避免重复计算

---

## 📦 系统要求

| 组件 | 最低版本 | 推荐版本 |
|------|---------|---------|
| **PrestaShop** | 1.7.0 | 1.7.8+ |
| **PHP** | 7.1 | 7.4+ / 8.0+ |
| **Smarty** | 3.1 | 4.3+ |
| **MySQL** | 5.6 | 5.7+ / 8.0+ |

**浏览器支持:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## 🚀 安装指南

### 方法 1: 通过 PrestaShop 后台安装

1. **下载模块压缩包**
   ```bash
   wget https://github.com/your-repo/zh_smartydevtools/releases/latest/zh_smartydevtools.zip
   ```

2. **上传到后台**
   - 登录 PrestaShop 后台
   - 进入 `模块 > 模块管理器`
   - 点击 `上传模块`
   - 选择 `zh_smartydevtools.zip`
   - 点击 `安装`

3. **启用开发模式**
   ```php
   // config/defines.inc.php
   define('_PS_MODE_DEV_', true);
   ```

### 方法 2: 手动安装

1. **克隆仓库**
   ```bash
   cd modules/
   git clone https://github.com/your-repo/zh_smartydevtools.git
   ```

2. **安装模块**
   ```bash
   cd /path/to/prestashop
   php bin/console prestashop:module install zh_smartydevtools
   ```

3. **清除缓存**
   ```bash
   rm -rf var/cache/dev/* var/cache/prod/*
   ```

### 方法 3: Composer 安装 (推荐开发环境)

```bash
composer require --dev zh/smartydevtools
php bin/console prestashop:module install zh_smartydevtools
```

---

## 🎯 快速入门

### 1. 启用模块

```bash
# 命令行安装
php bin/console prestashop:module install zh_smartydevtools

# 或通过后台: 模块 > 模块管理器 > 搜索 "Smarty Dev Tools" > 安装
```

### 2. 开启开发模式

```php
// config/defines.inc.php
define('_PS_MODE_DEV_', true);
```

### 3. 配置调试功能

访问后台配置页面：**模块 > 模块管理器 > Smarty Dev Tools > 配置**

#### 开关说明

| 开关 | 说明 | 依赖关系 |
|------|------|---------|
| **Smarty Dev Tools (总开关)** | 主控开关,控制模块整体启用状态 | 关闭时下方两个开关同时禁用并关闭 |
| **Element Comments** | 在 HTML 源代码中插入调试注释 | 依赖总开关开启 |
| **Structure Tree Viewer** | 在前台显示浮动按钮和结构树面板 | 依赖总开关**和** Element Comments 都开启 |

#### 推荐配置流程

```
步骤 1: 开启总开关
  ↓
步骤 2: 开启 Element Comments (此时 Structure Tree Viewer 变为可用)
  ↓
步骤 3: 根据需要选择是否开启 Structure Tree Viewer
  - 只需要 HTML 注释 → 保持关闭
  - 需要可视化调试 → 开启
```

### 4. 查看调试信息

访问前台任意页面：

- **仅开启 Element Comments**: 页面 HTML 源代码中包含调试注释,但无可见界面
- **同时开启 Structure Tree Viewer**: 右下角会出现浮动按钮

```
┌─────────────────────┐
│  🛠️ Smarty Debug    │
└─────────────────────┘
```

### 5. 打开调试面板

点击浮动按钮,弹出模态框显示 7 个标签页:

| 标签页 | 显示内容 |
|--------|---------|
| **Structure Tree** | 完整模板结构树(包含 extends 继承链) |
| **Extends** | 模板继承关系 |
| **Includes** | 包含的模板文件 |
| **Blocks** | 所有 block 定义 |
| **Hooks** | 所有 hook 调用 |
| **Widgets** | 所有 widget 组件 |
| **Module Fetches** | 模块模板获取 |
| **Evals** | 动态求值内容 |

---

## 📖 使用指南

### 查看模板继承链

**场景:** 想知道当前页面使用了哪些父模板

**操作步骤:**

1. 打开调试面板
2. 切换到 **"Extends"** 标签页
3. 查看完整继承链

**示例输出:**

```
模板继承链 (从当前页面到最终父模板):

Level 1: 📄 D:/www/webs/ps/.../templates/index.tpl (当前页面)
         ↑ extends
Level 2: 📑 layouts/layout-full-width.tpl (中间层)
         ↑ extends
Level 3: 📋 layouts/layout-both-columns.tpl (最终父模板)

总计 3 层继承
```

### 查找 Block 定义位置

**场景:** 想知道某个 block 在哪个模板中定义

**操作步骤:**

1. 打开调试面板
2. 切换到 **"Blocks"** 标签页
3. 搜索 block 名称 (Ctrl+F)

**示例输出:**

```
Block 列表:

📦 head_seo
   所在模板: themes/laveliyatheme/templates/_partials/head.tpl

📦 product_list
   所在模板: themes/laveliyatheme/templates/catalog/listing/product-list.tpl

📦 footer
   所在模板: themes/laveliyatheme/templates/_partials/footer.tpl
```

### 追踪 Hook 调用

**场景:** 想知道哪些模块挂载了某个 hook

**操作步骤:**

1. 打开调试面板
2. 切换到 **"Hooks"** 标签页
3. 查找 hook 名称

**示例输出:**

```
Hook 调用列表:

🧷 displayHeader
   调用位置: themes/laveliyatheme/templates/layouts/layout-both-columns.tpl

🧷 displayProductListReviews
   调用位置: modules/zh_bestproducts/views/templates/hook/home-recommend.tpl

🧷 displayFooter
   调用位置: themes/laveliyatheme/templates/_partials/footer.tpl
```

### 分析模板结构树

**场景:** 想了解页面完整的模板嵌套关系

**操作步骤:**

1. 打开调试面板
2. 查看 **"Structure Tree"** 标签页(默认)
3. 展开/折叠节点查看层级

**示例树形结构:**

```
📁 Extends
  ├─ 📄 index.tpl
  │   └─ ↳ page.tpl
  │       └─ ↳ layouts/layout-both-columns.tpl

📦 head (block)
  ├─ 📄 head.tpl (include)
  │   ├─ 📦 head_seo (block)
  │   │   └─ 🧷 displayProductListReviews (hook)
  │   └─ 📦 stylesheets (block)

📦 header (block)
  └─ 📄 header.tpl (include)
      ├─ ⚙️ ps_customersignin (widget)
      └─ ⚙️ ps_shoppingcart (widget)
```

### 识别动态内容 (Eval)

**场景:** 发现某些内容无法在模板中找到

**操作步骤:**

1. 打开调试面板
2. 切换到 **"Evals"** 标签页
3. 查看警告标记

**示例输出:**

```
📊 $custom_content_block['footer-copy']
   所在模板: themes/laveliyatheme/templates/_partials/footer.tpl
   ⚠️ 警告: 运行时动态内容,嵌套标签不可见

📊 $dynamic_menu
   所在模板: themes/laveliyatheme/templates/_partials/header.tpl
   ⚠️ 警告: 运行时动态内容,嵌套标签不可见
```

---

## 🔧 扩展开发

### 架构概览

```
TagProcessorFactory (工厂类)
    │
    ├─ SimpleTagProcessor (简单标签: extends, hook, widget)
    ├─ ComplexTagProcessor (复杂标签: include)
    ├─ BlockTagProcessor (配对标签: block)
    └─ EvalTagProcessor (动态标签: eval)
         ↓
    TagProcessorInterface (统一接口)
         ↓
    CommonUtils (共享工具)
         ↓
    StructureVisualizer (可视化渲染)
```

### 扩展示例 1: 添加 `{section}` 标签支持

**需求:** 追踪 Smarty `{section}` 循环标签

**步骤 1: 创建处理器类**

```php
<?php
// modules/zh_smartydevtools/classes/Processors/SectionTagProcessor.php

require_once dirname(__FILE__) . '/../Contracts/TagProcessorInterface.php';
require_once dirname(__FILE__) . '/../Utils/CommonUtils.php';

/**
 * Section 标签处理器
 *
 * 处理 Smarty {section} 循环标签:
 * {section name='products' loop=$products}
 *   {$products[products].name}
 * {/section}
 */
class SectionTagProcessor implements TagProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process($source, $smarty, $cleanSource)
    {
        $currentTemplate = CommonUtils::getCurrentTemplatePath($smarty);
        $safeTemplate = CommonUtils::sanitizePathForComment($currentTemplate);

        // 处理开始标签
        $source = preg_replace_callback(
            '/\{section\s+name=([\'"])([^\'"]+)\1([^\}]*)\}/s',
            function ($matches) use ($safeTemplate, $cleanSource) {
                // 检查是否在注释中
                if (CommonUtils::isTagInComment($matches[0], $cleanSource)) {
                    return $matches[0];
                }

                $sectionName = $matches[2];
                $params = $matches[3];

                // 提取 loop 参数
                $loopVar = 'unknown';
                if (preg_match('/loop=\$?([^\s\}]+)/', $params, $loopMatch)) {
                    $loopVar = $loopMatch[1];
                }

                return "<!-- START SECTION: {$sectionName} (loop: \${$loopVar}) (in: {$safeTemplate}) -->\n" .
                       $matches[0];
            },
            $source
        );

        // 处理结束标签
        $source = preg_replace_callback(
            '/\{\/section\}/s',
            function ($matches) use ($cleanSource) {
                if (CommonUtils::isTagInComment($matches[0], $cleanSource)) {
                    return $matches[0];
                }

                return $matches[0] . "\n<!-- END SECTION -->";
            },
            $source
        );

        return $source;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTags()
    {
        return ['section'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTagType()
    {
        return 'paired'; // 配对标签类型
    }
}
```

**步骤 2: 注册到工厂**

```php
// modules/zh_smartydevtools/classes/TagProcessorFactory.php

require_once dirname(__FILE__) . '/Processors/SectionTagProcessor.php';  // 新增

class TagProcessorFactory
{
    public static function getProcessors()
    {
        return [
            new SimpleTagProcessor(),
            new ComplexTagProcessor(),
            new BlockTagProcessor(),
            new EvalTagProcessor(),
            new SectionTagProcessor(),  // 新增这一行!
        ];
    }
}
```

**步骤 3: 添加可视化支持 (可选)**

```php
// modules/zh_smartydevtools/classes/StructureVisualizer.php

protected static function buildTemplateStructureTree($html)
{
    preg_match_all(
        '/<!-- (EXTENDS|START INCLUDE|...|START SECTION|END SECTION):\s*(.*?)\s*-->/s',
        //                                    ^^^^^^^^^^^^^^^^^^^^^^ 新增
        $html,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $match) {
        switch ($type) {
            // ... 其他 case

            case 'START SECTION':  // 新增
                if (preg_match('/^([^\(]+)\s*\(loop:\s*([^\)]+)\).*\(in:\s*([^\)]+)\)$/s', $content, $m)) {
                    self::addNodeToStructure($structure, [
                        'type' => 'section',
                        'name' => trim($m[1]),
                        'loop' => trim($m[2]),
                        'template' => trim($m[3]),
                        'depth' => count($structure['currentPath']),
                        'children' => []
                    ]);
                }
                break;

            case 'END SECTION':  // 新增
                self::popCurrentPath($structure);
                break;
        }
    }
}

protected static function renderStructureNode($node)
{
    switch ($node['type']) {
        // ... 其他 case

        case 'section':  // 新增
            $html .= '<span class="node-icon">🔄</span>';
            $html .= '<span class="section-node">' . htmlspecialchars($node['name']) . '</span>';
            $html .= '<span class="node-details">(loop: ' . htmlspecialchars($node['loop']) .
                     ' in: ' . htmlspecialchars($node['template']) . ')</span>';
            break;
    }
}
```

**步骤 4: 测试**

创建测试模板:

```smarty
{* test-section.tpl *}
{section name='products' loop=$products}
  <div>Product: {$products[products].name}</div>
  {section name='images' loop=$products[products].images}
    <img src="{$products[products].images[images].url}">
  {/section}
{/section}
```

清除缓存并访问页面:

```bash
rm -rf var/cache/dev/* var/cache/prod/*
```

**预期输出 (Structure Tree):**

```
🔄 products (section)
   loop: $products
   in: themes/.../test-section.tpl
   └─ 🔄 images (section)
      loop: $products[products].images
      in: themes/.../test-section.tpl
```

---

### 扩展示例 2: 添加性能监控

**需求:** 记录每个处理器的执行时间

**步骤 1: 修改工厂类**

```php
// modules/zh_smartydevtools/classes/TagProcessorFactory.php

class TagProcessorFactory
{
    protected static $performanceLog = [];

    public static function processWithProfiling($source, $smarty)
    {
        $cleanSource = CommonUtils::cleanSource($source);

        foreach (self::getProcessors() as $processor) {
            $startTime = microtime(true);

            $source = $processor->process($source, $smarty, $cleanSource);

            $duration = microtime(true) - $startTime;
            $processorName = get_class($processor);

            self::$performanceLog[$processorName] = [
                'duration' => $duration,
                'duration_ms' => round($duration * 1000, 2),
                'tags' => implode(', ', $processor->getSupportedTags()),
            ];
        }

        return $source;
    }

    public static function getPerformanceLog()
    {
        return self::$performanceLog;
    }

    public static function getPerformanceSummary()
    {
        $totalTime = 0;
        $summary = [];

        foreach (self::$performanceLog as $processor => $data) {
            $totalTime += $data['duration'];
            $summary[] = sprintf(
                "%s: %s ms (%s)",
                basename(str_replace('\\', '/', $processor)),
                $data['duration_ms'],
                $data['tags']
            );
        }

        $summary[] = "Total: " . round($totalTime * 1000, 2) . " ms";

        return implode("\n", $summary);
    }
}
```

**步骤 2: 在主处理器中使用**

```php
// modules/zh_smartydevtools/classes/SmartyDevProcessor.php

public static function processDevComments($source, $smarty)
{
    if (!Configuration::get('SMARTY_DEV_TOOLS_ENABLED')) {
        return $source;
    }

    // 使用性能监控版本
    $source = TagProcessorFactory::processWithProfiling($source, $smarty);

    // 记录性能日志
    if (_PS_MODE_DEV_) {
        $logFile = _PS_ROOT_DIR_ . '/var/logs/smarty_dev_performance.log';
        $logContent = date('[Y-m-d H:i:s] ') . $smarty->template_resource . "\n" .
                      TagProcessorFactory::getPerformanceSummary() . "\n\n";
        file_put_contents($logFile, $logContent, FILE_APPEND);
    }

    return $source;
}
```

**步骤 3: 查看性能日志**

```bash
tail -f var/logs/smarty_dev_performance.log
```

**输出示例:**

```
[2025-12-10 14:30:15] themes/laveliyatheme/templates/index.tpl
SimpleTagProcessor: 0.45 ms (extends, hook, widget)
ComplexTagProcessor: 1.23 ms (include)
BlockTagProcessor: 2.67 ms (block)
EvalTagProcessor: 0.31 ms (eval)
SectionTagProcessor: 0.52 ms (section)
Total: 5.18 ms
```

---

### 扩展示例 3: 自定义可视化样式

**需求:** 为特定项目定制可视化界面样式

**步骤 1: 创建自定义 CSS**

```css
/* modules/zh_smartydevtools/views/css/custom-theme.css */

/* 暗黑主题 */
.smarty-modal-content.dark-theme {
    background: #1e1e1e;
    color: #d4d4d4;
}

.smarty-modal-content.dark-theme .smarty-modal-header {
    background: #2d2d30;
    border-bottom: 1px solid #3e3e42;
}

.smarty-modal-content.dark-theme .tablink {
    background: #2d2d30;
    color: #d4d4d4;
}

.smarty-modal-content.dark-theme .tablink.active {
    background: #0e639c;
}

.smarty-modal-content.dark-theme .structure-tree .node-icon {
    filter: brightness(1.5);
}

/* 自定义节点颜色 */
.structure-tree .section-node {
    color: #9cdcfe;  /* 蓝色 */
    font-weight: 600;
}

.structure-tree .custom-node {
    color: #ce9178;  /* 橙色 */
    font-style: italic;
}
```

**步骤 2: 在模板中引入**

```php
// modules/zh_smartydevtools/classes/StructureVisualizer.php

public static function addTemplateStructureViewer($output, $smarty)
{
    // ... 现有代码

    $viewerSmarty->assign([
        'structure_tree_html' => $structure_tree_html,
        'module_dir' => $moduleDir,
        'custom_css' => true,  // 新增标志
    ]);

    // ...
}
```

```smarty
{* modules/zh_smartydevtools/views/templates/admin/structure_viewer.tpl *}

{if $custom_css}
<link rel="stylesheet" href="{$module_dir}views/css/custom-theme.css">
{/if}

<div id="smarty-structure-visualizer">
    <button id="smarty-structure-btn">🛠️ Smarty Debug</button>

    <div class="smarty-modal">
        <div class="smarty-modal-content dark-theme"> {* 应用暗黑主题 *}
            {* ... *}
        </div>
    </div>
</div>
```

---

## 🐛 故障排查

### 问题 1: 浮动按钮不显示

**可能原因:**
- 开发模式未启用
- 模块未激活
- Structure Tree Viewer 未开启
- Element Comments 未开启 (前置条件)

**解决方案:**

```php
// 1. 检查开发模式
// config/defines.inc.php
var_dump(_PS_MODE_DEV_);  // 应该输出 true

// 2. 检查模块状态
SELECT * FROM ps_module WHERE name = 'zh_smartydevtools';
// active 字段应为 1

// 3. 检查总开关
SELECT value FROM ps_configuration WHERE name = 'SMARTY_DEV_TOOLS_ENABLED';
// value 应为 '1'

// 4. 检查 Cookie (浏览器控制台执行)
document.cookie.split(';').filter(c => c.includes('smarty_show'))
// 应该看到: smarty_show_comments=1; smarty_show_viewer=1
```

### 问题 2: Structure Tree Viewer 无法开启

**可能原因:**
- Element Comments 未开启 (必须先开启)
- 总开关未开启

**解决方案:**

```
1. 检查总开关是否开启
2. 检查 Element Comments 是否开启
3. 按照依赖顺序开启: 总开关 → Element Comments → Structure Tree Viewer
```

**可能原因:**
- Smarty 对象缺少路径信息
- 模板是动态生成的 (eval)

**解决方案:**

```php
// 添加调试日志
// modules/zh_smartydevtools/classes/Utils/CommonUtils.php

public static function getCurrentTemplatePath($smarty)
{
    // 调试: 输出 Smarty 对象信息
    if (_PS_MODE_DEV_) {
        $debug = [
            '_source' => isset($smarty->_source) ? get_object_vars($smarty->_source) : null,
            'template_resource' => $smarty->template_resource ?? null,
            '_inheritance' => isset($smarty->_inheritance) ? count($smarty->_inheritance->sources ?? []) : 0,
        ];
        error_log("Smarty Path Debug: " . print_r($debug, true));
    }

    // ... 现有代码
}
```

### 问题 3: 模板路径显示为 "unknown_template"

**可能原因:**
- Smarty 对象缺少路径信息
- 模板是动态生成的 (eval)

**解决方案:**

```php
// 添加调试日志
// modules/zh_smartydevtools/classes/Utils/CommonUtils.php

public static function getCurrentTemplatePath($smarty)
{
    // 调试: 输出 Smarty 对象信息
    if (_PS_MODE_DEV_) {
        $debug = [
            '_source' => isset($smarty->_source) ? get_object_vars($smarty->_source) : null,
            'template_resource' => $smarty->template_resource ?? null,
            '_inheritance' => isset($smarty->_inheritance) ? count($smarty->_inheritance->sources ?? []) : 0,
        ];
        error_log("Smarty Path Debug: " . print_r($debug, true));
    }

    // ... 现有代码
}
```

### 问题 4: 结构树中缺少某些标签

**可能原因:**
- 标签在注释中
- 标签格式不标准
- 处理器未注册

**解决方案:**

```bash
# 1. 查看编译后的模板
cat var/cache/dev/smarty/compile/*/your_template.tpl.php | grep -A5 -B5 "YOUR_TAG"

# 2. 检查 HTML 注释
curl http://localhost/your-page | grep -o "<!-- START.*-->" | head -20

# 3. 验证处理器注册
grep -r "getSupportedTags" modules/zh_smartydevtools/classes/Processors/
```

### 问题 5: 性能下降

**可能原因:**
- 日志文件过大
- 处理器效率低

**解决方案:**

```bash
# 1. 清理日志
rm var/logs/smarty_dev*.log

# 2. 禁用不需要的处理器
# modules/zh_smartydevtools/classes/TagProcessorFactory.php
public static function getProcessors()
{
    $processors = [
        new SimpleTagProcessor(),
        new ComplexTagProcessor(),
        // new BlockTagProcessor(),  // 临时禁用
        // new EvalTagProcessor(),   // 临时禁用
    ];

    return $processors;
}

# 3. 清除缓存
rm -rf var/cache/dev/* var/cache/prod/*
```

---

## ⚡ 性能优化

### 生产环境配置

**完全禁用 (推荐):**

```php
// config/defines.inc.php
define('_PS_MODE_DEV_', false);  // 生产环境关闭开发模式
```

模块会自动检测 `_PS_MODE_DEV_`,在生产环境零性能损耗。

### 条件启用

**仅对特定 IP 启用:**

```php
// modules/zh_smartydevtools/zh_smartydevtools.php

public function hookActionDispatcherBefore($params)
{
    // 仅允许特定 IP 访问
    $allowedIPs = ['127.0.0.1', '192.168.1.100'];
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($clientIP, $allowedIPs)) {
        return;
    }

    // ... 原有代码
}
```

### 缓存优化

**缓存路径解析结果:**

```php
// modules/zh_smartydevtools/classes/Utils/CommonUtils.php

protected static $pathCache = [];

public static function getCurrentTemplatePath($smarty)
{
    // 使用缓存键
    $cacheKey = spl_object_hash($smarty);

    if (isset(self::$pathCache[$cacheKey])) {
        return self::$pathCache[$cacheKey];
    }

    // ... 路径解析逻辑

    self::$pathCache[$cacheKey] = $path;
    return $path;
}
```

---

## ❓ 常见问题

### Q1: 模块会影响生产环境性能吗?

**A:** 不会。模块在 `_PS_MODE_DEV_ = false` 时完全不执行,零性能损耗。

### Q2: 支持哪些 Smarty 版本?

**A:** Smarty 3.1+ 和 4.x 都支持。推荐使用 Smarty 4.3+ 以获得最佳体验。

### Q3: 可以在移动端使用吗?

**A:** 可以。界面已做响应式适配,支持手机和平板访问。

### Q4: 为什么 Structure Tree Viewer 无法单独开启?

**A:** 这是设计使然。Structure Tree Viewer 依赖 Element Comments 生成的 HTML 注释来构建结构树，因此必须先开启 Element Comments。这种单向依赖关系确保了功能的逻辑一致性。

### Q5: 如何隐藏浮动按钮?

**A:** 在后台配置中关闭 Structure Tree Viewer 开关，或删除对应的 Cookie:

```javascript
// 浏览器控制台执行
document.cookie = "smarty_show_viewer=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
```

### Q7: 支持多语言吗?

**A:** 当前为英文界面。如需中文化,修改模板文件中的文本即可。

### Q8: 模块会修改 Smarty 核心吗?

**A:** 不会。模块仅使用 Smarty 的 prefilter 和 outputfilter 钩子,不修改核心代码。

---

## 🤝 贡献指南

欢迎贡献代码、报告问题或提出建议!

### 报告 Bug

1. 访问 [GitHub Issues](https://github.com/your-repo/zh_smartydevtools/issues)
2. 点击 "New Issue"
3. 选择 "Bug Report" 模板
4. 填写以下信息:
   - PrestaShop 版本
   - PHP 版本
   - 模块版本
   - 复现步骤
   - 预期行为
   - 实际行为
   - 错误日志

### 提交功能请求

1. 访问 [GitHub Issues](https://github.com/your-repo/zh_smartydevtools/issues)
2. 点击 "New Issue"
3. 选择 "Feature Request" 模板
4. 描述功能需求和使用场景

### 贡献代码

1. **Fork 仓库**
   ```bash
   git clone https://github.com/your-username/zh_smartydevtools.git
   cd zh_smartydevtools
   ```

2. **创建分支**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **编写代码**
   - 遵循 PSR-2 编码规范
   - 添加必要的注释
   - 编写单元测试 (如果适用)

4. **提交更改**
   ```bash
   git add .
   git commit -m "feat: add your feature description"
   ```

5. **推送到 GitHub**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **创建 Pull Request**
   - 访问仓库页面
   - 点击 "New Pull Request"
   - 选择你的分支
   - 填写 PR 描述

### 代码规范

```php
<?php
/**
 * 类文件必须有文件头注释
 *
 * @author Your Name
 * @copyright 2025
 * @license AFL 3.0
 */

/**
 * 类必须有 PHPDoc 注释
 */
class YourClass
{
    /**
     * 方法必须有 PHPDoc 注释
     *
     * @param string $param 参数描述
     * @return bool 返回值描述
     */
    public function yourMethod($param)
    {
        // 使用 4 空格缩进
        if ($condition) {
            return true;
        }

        return false;
    }
}
```

---

## 📄 许可证

本项目采用 [Academic Free License 3.0](LICENSE) 许可。

---

## 🙏 致谢

- [PrestaShop](https://www.prestashop.com/) - 优秀的电商平台
- [Smarty](https://www.smarty.net/) - 强大的模板引擎
- 所有贡献者和用户

---

## 📞 联系方式

- **GitHub:** https://github.com/your-repo/zh_smartydevtools
- **Issues:** https://github.com/your-repo/zh_smartydevtools/issues
- **Email:** your-email@example.com

---

## 📝 更新日志

### v2.0.0 (2025-12-11)

**重大更新:**
- ✨ 改进联动逻辑: Element Comments 作为 Structure Tree Viewer 的前置条件
- 🎨 AJAX 即时生效: 移除 Save 按钮,切换开关即生效
- 🔒 智能状态同步: 前后端状态完全同步,UI 准确反映 Cookie 状态
- 🛡️ 防重复提交: 内置请求锁机制
- 💾 Cookie 管理优化: 8小时自动过期,总开关关闭时自动清理

**架构优化:**
- 重构为 AJAX 模式,提升用户体验
- 优化联动逻辑,符合依赖关系直觉
- 改进错误处理和用户提示

---

**最后更新:** 2025-12-11
**版本:** 2.0.0

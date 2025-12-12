<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Zh_smartydevtools extends Module
{
    public function __construct()
    {
        $this->name = 'zh_smartydevtools';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->author = 'zzh';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Smarty Development Tools');
        $this->description = $this->l('Adds development comments and visual tools for Smarty templates in PrestaShop.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        // 移除所有调试cookie
        $this->removeAllCookies();
        return parent::install() &&
            $this->registerHook('actionDispatcherBefore') &&
            Configuration::updateValue('SMARTY_DEV_TOOLS_ENABLED', 1);
    }

    public function uninstall()
    {
        // 移除所有调试cookie
        $this->removeAllCookies();
        // 清除Smarty缓存
        $this->clearSmartyCache();
        return Configuration::deleteByName('SMARTY_DEV_TOOLS_ENABLED') &&
            parent::uninstall();
    }

    public function hookActionDispatcherBefore($params)
    {
        // 检查总开关状态
        $enabled = Configuration::get('SMARTY_DEV_TOOLS_ENABLED');

        // 如果总开关关闭，但浏览器仍有调试Cookie，自动清理（处理多浏览器场景）
        if (!$enabled) {
            $hasCookies = (isset($_COOKIE['smarty_show_comments']) && $_COOKIE['smarty_show_comments'] == '1') ||
                (isset($_COOKIE['smarty_show_viewer']) && $_COOKIE['smarty_show_viewer'] == '1');

            if ($hasCookies) {
                $this->removeAllCookies();
            }
            return; // 总开关关闭，直接返回
        }

        // 只在启用模块且不在后台时启用
        if (!defined('_PS_ADMIN_DIR_')) {
            // 加载核心处理器类 (自动加载 TagProcessorFactory 和 StructureVisualizer)
            require_once _PS_MODULE_DIR_ . $this->name . '/classes/SmartyDevProcessor.php';

            global $smarty;

            // 检查元素注释开关 - 仅基于当前浏览器 Cookie
            $showComments = isset($_COOKIE['smarty_show_comments']) &&
                $_COOKIE['smarty_show_comments'] == '1';

            if ($showComments) {
                // 注册预编译过滤器 (添加开发注释)
                $smarty->registerFilter('pre', array('SmartyDevProcessor', 'processDevComments'));

                // 注册模块资源处理器
                $this->registerModuleResourceWithComments($smarty);
            }

            // 检查结构树按钮开关 - 仅基于当前浏览器 Cookie
            $showViewer = isset($_COOKIE['smarty_show_viewer']) &&
                $_COOKIE['smarty_show_viewer'] == '1';

            if ($showViewer) {
                $smarty->registerFilter('output', array('SmartyDevProcessor', 'addTemplateStructureViewer'));
            }
        }
    }
    /**
     * 注册带注释的模块资源处理器
     */
    protected function registerModuleResourceWithComments($smarty)
    {
        require_once _PS_MODULE_DIR_ . $this->name . '/classes/SmartyResourceModuleWithComments.php';

        // 获取当前注册的模块资源路径
        $module_resources = array('theme' => _PS_THEME_DIR_ . 'modules/');
        if (_PS_PARENT_THEME_DIR_ !== '') {
            $module_resources['parent'] = _PS_PARENT_THEME_DIR_ . 'modules/';
        }
        $module_resources['modules'] = _PS_MODULE_DIR_;

        // 注册带注释的模块资源
        $smarty->registerResource('module', new SmartyResourceModuleWithComments($module_resources));
    }

    public function getContent()
    {
        return $this->renderForm();
    }
    /**
     * 清除Smarty缓存
     */
    protected function clearSmartyCache()
    {
        // 清除编译模板
        Tools::clearSmartyCache();

        // 清除缓存文件
        $cache_dirs = array(
            _PS_CACHE_DIR_ . 'smarty/compile/',
            _PS_CACHE_DIR_ . 'smarty/cache/',
        );

        foreach ($cache_dirs as $dir) {
            if (is_dir($dir)) {
                foreach (scandir($dir) as $file) {
                    if ($file[0] != '.' && $file != 'index.php') {
                        if (is_file($dir . $file)) {
                            unlink($dir . $file);
                        } elseif (is_dir($dir . $file)) {
                            Tools::deleteDirectory($dir . $file);
                        }
                    }
                }
            }
        }

        // 如果启用了OPCache，也需要清除
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    public function renderForm()
    {
        $enabled = (int) Configuration::get('SMARTY_DEV_TOOLS_ENABLED');
        $showComments = $enabled && isset($_COOKIE['smarty_show_comments']) && $_COOKIE['smarty_show_comments'] == '1';
        $showViewer = $enabled && isset($_COOKIE['smarty_show_viewer']) && $_COOKIE['smarty_show_viewer'] == '1';

        $this->context->smarty->assign([
            'enabled' => $enabled,
            'show_comments' => $showComments,
            'show_viewer' => $showViewer,
            'ajax_url' => $this->context->link->getAdminLink('AdminModules', true),
            'module_name' => $this->name,
            'module_dir' => $this->_path,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * AJAX方法：切换开关
     */
    public function ajaxProcessToggleSwitch()
    {
        $switchType = Tools::getValue('switch');
        $state = (int) Tools::getValue('state');
        $response = ['success' => false];

        try {
            switch ($switchType) {
                case 'enabled':
                    Configuration::updateValue('SMARTY_DEV_TOOLS_ENABLED', $state);
                    if (!$state) {
                        // 总开关关闭时移除所有cookie
                        $this->removeAllCookies();
                        // 联动关闭下方两个开关的UI
                        $response['linkedToggles'] = [
                            ['switch' => 'comments', 'state' => false],
                            ['switch' => 'viewer', 'state' => false]
                        ];
                    }
                    $response['success'] = true;
                    $response['message'] = $state ?
                        $this->l('Smarty Dev Tools enabled') :
                        $this->l('Smarty Dev Tools disabled');

                    // 总开关开启时，viewer的可用状态取决于comments的实际状态
                    if ($state) {
                        $commentsCookie = isset($_COOKIE['smarty_show_comments']) && $_COOKIE['smarty_show_comments'] == '1';
                        $response['disableStates'] = [
                            'comments' => true,
                            'viewer' => $commentsCookie  // 只有comments开启时viewer才可用
                        ];
                    } else {
                        $response['disableStates'] = [
                            'comments' => false,
                            'viewer' => false
                        ];
                    }
                    break;

                case 'comments':
                    $enabled = (int) Configuration::get('SMARTY_DEV_TOOLS_ENABLED');
                    if (!$enabled) {
                        $response['message'] = $this->l('Please enable Smarty Dev Tools first');
                        break;
                    }

                    if ($state) {
                        $this->setCookie('smarty_show_comments');
                        // comments开启时，viewer变为可用（但不自动开启）
                        $response['updateViewerDisabled'] = true;
                    } else {
                        $this->removeCookie('smarty_show_comments');
                        // comments关闭时：
                        // 1. 联动关闭viewer的cookie
                        $viewerCookie = isset($_COOKIE['smarty_show_viewer']) && $_COOKIE['smarty_show_viewer'] == '1';
                        if ($viewerCookie) {
                            $this->removeCookie('smarty_show_viewer');
                            $response['linkedToggle'] = [
                                'switch' => 'viewer',
                                'state' => false
                            ];
                        }
                        // 2. 禁用viewer开关
                        $response['updateViewerDisabled'] = false;
                    }
                    $response['success'] = true;
                    $response['message'] = $state ?
                        $this->l('Element Comments enabled') :
                        $this->l('Element Comments disabled');
                    break;

                case 'viewer':
                    $enabled = (int) Configuration::get('SMARTY_DEV_TOOLS_ENABLED');
                    if (!$enabled) {
                        $response['message'] = $this->l('Please enable Smarty Dev Tools first');
                        break;
                    }

                    if ($state) {
                        // viewer开启前检查comments是否已开启（前端已禁用，这里做后端校验）
                        $commentsCookie = isset($_COOKIE['smarty_show_comments']) && $_COOKIE['smarty_show_comments'] == '1';
                        if (!$commentsCookie) {
                            $response['success'] = false;
                            $response['message'] = $this->l('Please enable Element Comments first');
                            break;
                        }
                        $this->setCookie('smarty_show_viewer');
                    } else {
                        $this->removeCookie('smarty_show_viewer');
                    }
                    $response['success'] = true;
                    $response['message'] = $state ?
                        $this->l('Structure Tree Viewer enabled') :
                        $this->l('Structure Tree Viewer disabled');
                    break;

                default:
                    $response['message'] = $this->l('Invalid switch type');
            }

            // 清除Smarty缓存使更改立即生效
            if ($response['success']) {
                $this->clearSmartyCache();
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    /**
     * AJAX方法：清除Smarty缓存
     */
    public function ajaxProcessClearSmartyCache()
    {
        $this->clearSmartyCache();
        die(json_encode(['success' => true, 'message' => $this->l('Smarty cache cleared successfully')]));
    }

    /**
     * 设置指定的cookie
     */
    private function setCookie($cookieName)
    {
        setcookie($cookieName, '1', time() + 28800, '/', '', false, true);
        $_COOKIE[$cookieName] = '1'; // 立即更新当前请求的$_COOKIE
    }

    /**
     * 移除指定的cookie
     */
    private function removeCookie($cookieName)
    {
        setcookie($cookieName, '', time() - 3600, '/', '', false, true);
        if (isset($_COOKIE[$cookieName])) {
            unset($_COOKIE[$cookieName]);
        }
    }
    /**
     * 移除所有调试cookie
     */
    private function removeAllCookies()
    {
        $cookies = ['smarty_show_viewer', 'smarty_show_comments'];
        foreach ($cookies as $cookie) {
            setcookie($cookie, '', time() - 3600, '/', '', false, true);
            if (isset($_COOKIE[$cookie])) {
                unset($_COOKIE[$cookie]);
            }
        }
    }
}

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
        // 只在启用模块且不在后台时启用
        if (!defined('_PS_ADMIN_DIR_') && Configuration::get('SMARTY_DEV_TOOLS_ENABLED')) {
            // 加载核心处理器类 (自动加载 TagProcessorFactory 和 StructureVisualizer)
            require_once _PS_MODULE_DIR_ . $this->name . '/classes/SmartyDevProcessor.php';

            global $smarty;

            // 检查元素注释开关 - 从配置读取并检查cookie
            $showComments = Configuration::get('SMARTY_SHOW_COMMENTS') &&
                isset($_COOKIE['smarty_show_comments']) &&
                $_COOKIE['smarty_show_comments'] == '1';

            if ($showComments) {
                // 注册预编译过滤器 (添加开发注释)
                $smarty->registerFilter('pre', array('SmartyDevProcessor', 'processDevComments'));

                // 注册模块资源处理器
                $this->registerModuleResourceWithComments($smarty);
            }

            // 检查结构树按钮开关 - 从配置读取并检查cookie
            $showViewer = Configuration::get('SMARTY_SHOW_VIEWER') &&
                isset($_COOKIE['smarty_show_viewer']) &&
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
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $enabled = (int)Tools::getValue('SMARTY_DEV_TOOLS_ENABLED');
            $showComments = (int)Tools::getValue('SMARTY_SHOW_COMMENTS');
            $showViewer = (int)Tools::getValue('SMARTY_SHOW_VIEWER');

            // 依赖关系处理: 如果开启结构树,自动开启元素注释
            if ($showViewer && !$showComments) {
                $showComments = 1;
                $output .= $this->displayWarning($this->l('Element Comments has been automatically enabled because Structure Tree Viewer requires it.'));
            }

            // 依赖关系处理: 如果关闭元素注释,自动关闭结构树
            if (!$showComments && $showViewer) {
                $showViewer = 0;
                $output .= $this->displayWarning($this->l('Structure Tree Viewer has been automatically disabled because Element Comments is required.'));
            }

            // 只保存总开关到数据库
            Configuration::updateValue('SMARTY_DEV_TOOLS_ENABLED', $enabled);

            // 根据总开关状态设置cookie(仅影响当前浏览器)
            if ($enabled) {
                // 总开关开启时,根据用户选择设置cookie
                if ($showComments) {
                    $this->setCookie('smarty_show_comments');
                } else {
                    $this->removeCookie('smarty_show_comments');
                }

                if ($showViewer) {
                    $this->setCookie('smarty_show_viewer');
                } else {
                    $this->removeCookie('smarty_show_viewer');
                }
            } else {
                // 总开关关闭时移除所有cookie
                $this->removeAllCookies();
            }

            $output .= $this->displayConfirmation($this->l('Settings updated (affects only your browser)'));

            // 清除Smarty缓存
            $this->clearSmartyCache();
        }

        return $output . $this->renderForm();
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
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Smarty Dev Tools'),
                        'name' => 'SMARTY_DEV_TOOLS_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('Enable development tools for Smarty templates.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Element Comments'),
                        'name' => 'SMARTY_SHOW_COMMENTS',
                        'is_bool' => true,
                        'desc' => $this->l('Show HTML comments in page source (<!-- START HOOK: ... -->). Affects only browsers with cookie set.'),
                        'values' => [
                            [
                                'id' => 'comments_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'comments_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Structure Tree Viewer'),
                        'name' => 'SMARTY_SHOW_VIEWER',
                        'is_bool' => true,
                        'desc' => $this->l('Display the structure tree viewer button at the bottom of pages. Note: Requires Element Comments to be enabled. Affects only browsers with cookie set.'),
                        'values' => [
                            [
                                'id' => 'viewer_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'viewer_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'clear_cache_html',
                        'html_content' => '
                            <div class="form-group">
                                <div class="col-lg-9 col-lg-offset-3">
                                    <button type="button" id="clear-smarty-cache" class="btn btn-default">
                                        <i class="icon-refresh"></i> ' . $this->l('Clear Smarty Cache') . '
                                    </button>
                                    <p class="help-block">' . $this->l('Clear Smarty cache if changes are not reflected immediately.') . '</p>
                                </div>
                            </div>
                            <script>
                                $(document).ready(function() {
                                    $("#clear-smarty-cache").click(function() {
                                        $.ajax({
                                            url: "' . $this->context->link->getAdminLink('AdminModules', true) . '",
                                            data: {
                                                configure: "' . $this->name . '",
                                                ajax: 1,
                                                action: "clearSmartyCache"
                                            },
                                            success: function(response) {
                                                showSuccessMessage("' . $this->l('Smarty cache cleared successfully') . '");
                                            }
                                        });
                                    });

                                    // 依赖关系处理: Structure Tree Viewer 开启时自动开启 Element Comments
                                    // PrestaShop switch 控件使用 radio button,需要监听 label 的 click 事件
                                    $("input[name=SMARTY_SHOW_VIEWER]").on("change", function() {
                                        if ($(this).val() == "1" && $(this).is(":checked")) {
                                            // 自动开启 Element Comments
                                            $("#comments_on").prop("checked", true).click();
                                        }
                                    });

                                    // 依赖关系处理: Element Comments 关闭时自动关闭 Structure Tree Viewer
                                    $("input[name=SMARTY_SHOW_COMMENTS]").on("change", function() {
                                        if ($(this).val() == "0" && $(this).is(":checked")) {
                                            // 自动关闭 Structure Tree Viewer
                                            $("#viewer_off").prop("checked", true).click();
                                        }
                                    });
                                });
                            </script>
                        ',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save')
                ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        $enabled = Tools::getValue(
            'SMARTY_DEV_TOOLS_ENABLED',
            Configuration::get('SMARTY_DEV_TOOLS_ENABLED')
        );

        return [
            'SMARTY_DEV_TOOLS_ENABLED' => $enabled,
            // 从当前浏览器Cookie读取状态(仅影响当前浏览器)
            // 如果总开关关闭,则强制显示为关闭状态
            'SMARTY_SHOW_COMMENTS' => $enabled ? Tools::getValue(
                'SMARTY_SHOW_COMMENTS',
                (isset($_COOKIE['smarty_show_comments']) && $_COOKIE['smarty_show_comments'] == '1') ? 1 : 0
            ) : 0,
            'SMARTY_SHOW_VIEWER' => $enabled ? Tools::getValue(
                'SMARTY_SHOW_VIEWER',
                (isset($_COOKIE['smarty_show_viewer']) && $_COOKIE['smarty_show_viewer'] == '1') ? 1 : 0
            ) : 0,
        ];
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

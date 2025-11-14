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
        $this->version = '1.0.0';
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
        // 移除调试cookie
        $this->removeCookie();
        return parent::install() &&
            $this->registerHook('actionDispatcherBefore') &&
            Configuration::updateValue('SMARTY_DEV_TOOLS_ENABLED', 1) &&
            Configuration::updateValue('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER', 0); // 默认关闭模板结构分析工具
    }

    public function uninstall()
    {
        // 移除调试cookie
        $this->removeCookie();
        // 清除Smarty缓存
        $this->clearSmartyCache();
        return Configuration::deleteByName('SMARTY_DEV_TOOLS_ENABLED') &&
            Configuration::deleteByName('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER') && // 删除新添加的配置项
            parent::uninstall();
    }

    public function hookActionDispatcherBefore($params)
    {
        // 只在启用模块且不在后台时启用，并检查cookie开关
        if (!defined('_PS_ADMIN_DIR_') && Configuration::get('SMARTY_DEV_TOOLS_ENABLED') && isset($_COOKIE['smarty_debug']) && $_COOKIE['smarty_debug'] == 1) {
            require_once _PS_MODULE_DIR_ . $this->name . '/classes/SmartyDevProcessor.php';
            
            global $smarty;
            $smarty->registerFilter('pre', array('SmartyDevProcessor', 'processDevComments'));
            
            // 仅在启用模板结构分析工具时注册输出过滤器
            if (Configuration::get('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER')) {
                $smarty->registerFilter('output', array('SmartyDevProcessor', 'addTemplateStructureViewer'));
            }
            
            $this->registerModuleResourceWithComments($smarty);
        }
    }
    
    /**
     * 注册带注释的模块资源处理器
     */
    protected function registerModuleResourceWithComments($smarty)
    {
        require_once _PS_MODULE_DIR_ . $this->name . '/classes/SmartyResourceModuleWithComments.php';
        
        // 获取当前注册的模块资源路径
        $module_resources = array('theme' => _PS_THEME_DIR_.'modules/');
        if (_PS_PARENT_THEME_DIR_ !== '') {
            $module_resources['parent'] = _PS_PARENT_THEME_DIR_.'modules/';
        }
        $module_resources['modules'] = _PS_MODULE_DIR_;
        
        // 注册带注释的模块资源
        $smarty->registerResource('module', new SmartyResourceModuleWithComments($module_resources));
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('set_debug_cookie')) {
            // 设置调试cookie
            $this->setCookie();
            $output .= $this->displayConfirmation($this->l('Debug cookie has been set. Refresh the frontend page to see the changes.'));
        } elseif (Tools::isSubmit('remove_debug_cookie')) {
            // 移除调试cookie
            $this->removeCookie();
            $output .= $this->displayConfirmation($this->l('Debug cookie has been removed. Refresh the frontend page to see the changes.'));
        } elseif (Tools::isSubmit('submit' . $this->name)) {
            $enabled = (int)Tools::getValue('SMARTY_DEV_TOOLS_ENABLED');
            $templateViewer = (int)Tools::getValue('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER');
            
            Configuration::updateValue('SMARTY_DEV_TOOLS_ENABLED', $enabled);
            Configuration::updateValue('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER', $templateViewer);

            if(!$enabled){
                // 移除调试cookie
                $this->removeCookie();
            }

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        // 清除Smarty缓存
        $this->clearSmartyCache();

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
                        'label' => $this->l('Enable Template Structure Viewer'),
                        'name' => 'SMARTY_DEV_TOOLS_TEMPLATE_VIEWER',
                        'is_bool' => true,
                        'desc' => $this->l('Display the template structure viewer button at the bottom of pages.'),
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
                        'name' => 'cookie_buttons',
                        'html_content' => '
                            <div class="form-group">
                                <label class="control-label col-lg-3">' . $this->l('Debug Cookie') . '</label>
                                <div class="col-lg-9">
                                    <div class="row">
                                        <div class="col-lg-4">
                                            <button type="submit" name="set_debug_cookie" class="btn btn-default">
                                                <i class="icon-plus"></i> ' . $this->l('Set Debug Cookie') . '
                                            </button>
                                            <p class="help-block">' . $this->l('Sets a cookie in your browser to enable debugging tools.') . '</p>
                                        </div>
                                        <div class="col-lg-4">
                                            <button type="submit" name="remove_debug_cookie" class="btn btn-default">
                                                <i class="icon-trash"></i> ' . $this->l('Remove Debug Cookie') . '
                                            </button>
                                            <p class="help-block">' . $this->l('Removes the debug cookie from your browser.') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ',
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
        return [
            'SMARTY_DEV_TOOLS_ENABLED' => Tools::getValue('SMARTY_DEV_TOOLS_ENABLED', 
                Configuration::get('SMARTY_DEV_TOOLS_ENABLED')),
            'SMARTY_DEV_TOOLS_TEMPLATE_VIEWER' => Tools::getValue('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER',
                Configuration::get('SMARTY_DEV_TOOLS_TEMPLATE_VIEWER')),
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

    private function removeCookie()
    {
        // 移除调试cookie
        setcookie('smarty_debug', '', time() - 28800, '/', '', false, true);
    }

    private function setCookie()
    { 
        // 设置调试cookie
        setcookie('smarty_debug', '1', time() + 28800, '/', '', false, true);
    }
}
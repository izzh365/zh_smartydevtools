{*
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
*}
<div id="smarty-structure-visualizer">
    <!-- 浮动按钮 -->
    <div id="smarty-structure-btn" title="查看模板结构">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
            <path d="M8 11h8"></path>
            <path d="M8 7h6"></path>
            <path d="M8 15h4"></path>
        </svg>
    </div>

    <!-- 模态框 -->
    <div id="smarty-structure-modal" class="smarty-modal">
        <div class="smarty-modal-content">
            <div class="smarty-modal-body">
                <div class="smarty-tabs">
                    <button class="tablink active" data-tab="tree">结构树</button>
                </div>
                
                <div id="tree" class="tabcontent active">
                    <h3>模板结构树</h3>
                    {$structure_tree_html nofilter}
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="{$module_dir}views/templates/admin/structure_viewer.css" />
<script src="{$module_dir}views/templates/admin/structure_viewer.js"></script>

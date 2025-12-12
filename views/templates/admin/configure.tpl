{*
* Copyright since 2007 PrestaShop SA and Contributors
* PrestaShop is an International Registered Trademark & Property of PrestaShop SA
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License 3.0 (AFL-3.0)
* https://opensource.org/licenses/AFL-3.0
*
* @author    PrestaShop SA and Contributors <contact@prestashop.com>
* @copyright Since 2007 PrestaShop SA and Contributors
* @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
*}

<link rel="stylesheet" href="{$module_dir|escape:'htmlall':'UTF-8'}views/css/admin-configure.css">

<div class="panel smarty-dev-panel">
  <div class="panel-heading">
    <h3><i class="icon-cogs"></i> {l s='Smarty Development Tools Settings' mod='zh_smartydevtools'}</h3>
  </div>
  <div class="panel-body">
    <div class="smarty-dev-setting">
      <label>
        <span class="title">{l s='Enable Smarty Dev Tools' mod='zh_smartydevtools'}</span>
        <label class="smarty-toggle">
          <input type="checkbox" id="switch-enabled" {if $enabled}checked{/if}>
          <span class="smarty-slider"></span>
        </label>
      </label>
      <p class="desc">{l s='Master switch for all development tools.' mod='zh_smartydevtools'}</p>
    </div>

    <div class="smarty-dev-setting">
      <label>
        <span class="title">{l s='Element Comments' mod='zh_smartydevtools'}</span>
        <label class="smarty-toggle">
          <input type="checkbox" id="switch-comments" {if $show_comments}checked{/if} {if !$enabled}disabled{/if}>
          <span class="smarty-slider"></span>
        </label>
      </label>
      <p class="desc">
        {l s='Show HTML comments in page source (<!-- START HOOK: ... -->). Affects only your browser.' mod='zh_smartydevtools'}
      </p>
    </div>

    <div class="smarty-dev-setting">
      <label>
        <span class="title">{l s='Structure Tree Viewer' mod='zh_smartydevtools'}</span>
        <label class="smarty-toggle">
          <input type="checkbox" id="switch-viewer" {if $show_viewer}checked{/if}
            {if !$enabled || !$show_comments}disabled{/if}>
          <span class="smarty-slider"></span>
        </label>
      </label>
      <p class="desc">
        {l s='Display structure tree viewer button. Requires Element Comments to be enabled. Affects only your browser.' mod='zh_smartydevtools'}
      </p>
    </div>

    <div class="cache-btn">
      <button type="button" id="clear-smarty-cache" class="btn btn-default">
        <i class="icon-refresh"></i> {l s='Clear Smarty Cache' mod='zh_smartydevtools'}
      </button>
    </div>
  </div>
</div>

<script>
  (function($) {
    var ajaxUrl = "{$ajax_url|escape:'javascript':'UTF-8'}";
    var moduleName = "{$module_name|escape:'javascript':'UTF-8'}";
    var isProcessing = false;

    function toggleSwitch(switchId, newState, skipAjax) {
      var $switch = $("#" + switchId);
      if ($switch.length && $switch.prop("checked") !== newState) {
        $switch.prop("checked", newState);
        if (!skipAjax) {
          sendAjax(switchId.replace("switch-", ""), newState);
        }
      }
    }

    function sendAjax(switchType, state) {
      if (isProcessing) return;
      isProcessing = true;

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: {
          configure: moduleName,
          ajax: 1,
          action: "toggleSwitch",
          switch: switchType,
          state: state ? 1 : 0
        },
        dataType: "json",
        success: function(response) {
          if (response.success) {
            // 联动处理（单个开关）
            if (response.linkedToggle) {
              $("#switch-" + response.linkedToggle.switch).prop("checked", response.linkedToggle.state);
            }
            // 联动处理（批量开关，如总开关关闭时）
            if (response.linkedToggles) {
              $.each(response.linkedToggles, function(index, toggle) {
                $("#switch-" + toggle.switch).prop("checked", toggle.state);
              });
            }
            // 禁用状态更新
            if (response.disableStates) {
              $("#switch-comments").prop("disabled", !response.disableStates.comments);
              $("#switch-viewer").prop("disabled", !response.disableStates.viewer);
            }
            // comments状态变化时更新viewer的禁用状态
            if (response.updateViewerDisabled !== undefined) {
              $("#switch-viewer").prop("disabled", !response.updateViewerDisabled);
            }
            if (response.message) {
              showSuccessMessage(response.message);
            }
          } else {
            showErrorMessage(response.message || "Operation failed");
          }
        },
        error: function() {
          showErrorMessage("AJAX request failed");
        },
        complete: function() {
          isProcessing = false;
        }
      });
    }

    $("#switch-enabled").on("change", function() {
      var state = $(this).prop("checked");
      sendAjax("enabled", state);
    });

    $("#switch-comments").on("change", function() {
      var state = $(this).prop("checked");
      sendAjax("comments", state);
    });

    $("#switch-viewer").on("change", function() {
      var state = $(this).prop("checked");
      sendAjax("viewer", state);
    });

    $("#clear-smarty-cache").on("click", function() {
      $.ajax({
        url: ajaxUrl,
        data: {
          configure: moduleName,
          ajax: 1,
          action: "clearSmartyCache"
        },
        success: function(response) {
          showSuccessMessage("{l s='Smarty cache cleared successfully' mod='zh_smartydevtools' js=1}");
        }
      });
    });
  })(jQuery);
</script>

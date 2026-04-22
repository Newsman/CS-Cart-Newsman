{capture name="mainbox"}
<form action="{""|fn_url}" method="post" class="form-horizontal form-edit" name="newsman_settings_form">
<input type="hidden" name="dispatch[newsman.save]" value="1" />

{* === Account Section === *}
<h4>{__("newsman.account_section")}
    <span class="badge" style="background-color:#28a745;color:#fff;font-size:11px;margin-left:8px;">v{$newsman_version}</span>
</h4>

<div class="control-group">
    <label class="control-label cm-required" for="newsman_user_id">{__("newsman.user_id")}:</label>
    <div class="controls">
        <input type="text" name="user_id" id="newsman_user_id" value="{$newsman_settings.user_id}" size="40" class="input-medium" />
    </div>
</div>

<div class="control-group">
    <label class="control-label cm-required" for="newsman_api_key">{__("newsman.api_key")}:</label>
    <div class="controls">
        <input type="text" name="api_key" id="newsman_api_key" value="{$newsman_settings.api_key}" size="60" class="input-large" />
    </div>
</div>

<div class="control-group">
    <label class="control-label">&nbsp;</label>
    <div class="controls">
        {if $newsman_connected}
            <span style="color:#28a745;">&#9679;</span> {__("newsman.connected")}
        {else}
            <span style="color:#dc3545;">&#9679;</span> {__("newsman.not_connected")}
        {/if}
    </div>
</div>

{* === General Section === *}
<hr />
<h4>{__("newsman.general_section")}</h4>

<div class="control-group">
    <label class="control-label cm-required" for="newsman_list_id">{__("newsman.list_id")}:</label>
    <div class="controls">
        <select name="list_id" id="newsman_list_id" class="input-xlarge" onchange="newsmanFetchSegments(this.value)">
            <option value="">-- {__("newsman.select_list")} --</option>
            {foreach from=$newsman_lists item=list}
                <option value="{$list.list_id}" {if $newsman_settings.list_id == $list.list_id}selected="selected"{/if}>{$list.list_name}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_segment_id">{__("newsman.segment_id")}:</label>
    <div class="controls">
        <select name="segment_id" id="newsman_segment_id" class="input-xlarge">
            <option value="">-- {__("newsman.no_segment")} --</option>
            {foreach from=$newsman_segments item=segment}
                <option value="{$segment.segment_id}" {if $newsman_settings.segment_id == $segment.segment_id}selected="selected"{/if}>{$segment.segment_name}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label cm-required" for="newsman_cscart_mailing_list_id">{__("newsman.cscart_mailing_list_id")}:</label>
    <div class="controls">
        <select name="cscart_mailing_list_id" id="newsman_cscart_mailing_list_id" class="input-xlarge">
            <option value="">-- {__("newsman.cscart_mailing_list_none")} --</option>
            {foreach from=$newsman_cscart_mailing_lists item=ml}
                <option value="{$ml.list_id}" {if $newsman_settings.cscart_mailing_list_id == $ml.list_id}selected="selected"{/if}>{$ml.name|default:$ml.object}</option>
            {/foreach}
        </select>
        <p class="description">
            <span class="label label-important" style="margin-right:6px;"><i class="icon-warning-sign icon-white"></i> {__("newsman.required")}</span>
            <span class="muted">{__("newsman.cscart_mailing_list_id_help")}</span>
            <br />
            <span class="muted">{__("newsman.cscart_mailing_list_id_warning")}</span>
        </p>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_double_optin">{__("newsman.double_optin")}:</label>
    <div class="controls">
        <input type="hidden" name="double_optin" value="N" />
        <input type="checkbox" name="double_optin" id="newsman_double_optin" value="Y" {if $newsman_settings.double_optin == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_send_user_ip">{__("newsman.send_user_ip")}:</label>
    <div class="controls">
        <input type="hidden" name="send_user_ip" value="N" />
        <input type="checkbox" name="send_user_ip" id="newsman_send_user_ip" value="Y" {if $newsman_settings.send_user_ip == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_server_ip">{__("newsman.server_ip")}:</label>
    <div class="controls">
        <input type="text" name="server_ip" id="newsman_server_ip" value="{$newsman_settings.server_ip}" size="30" class="input-medium" />
        <p class="muted description">{__("newsman.server_ip_help")}</p>
    </div>
</div>

{* === Remarketing Section === *}
<hr />
<h4>{__("newsman.remarketing_section")}</h4>

<div class="control-group">
    <label class="control-label" for="newsman_remarketing_status">{__("newsman.remarketing_status")}:</label>
    <div class="controls">
        <input type="hidden" name="remarketing_status" value="N" />
        <input type="checkbox" name="remarketing_status" id="newsman_remarketing_status" value="Y" {if $newsman_settings.remarketing_status == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_remarketing_id">{__("newsman.remarketing_id")}:</label>
    <div class="controls">
        <input type="text" name="remarketing_id" id="newsman_remarketing_id" value="{$newsman_settings.remarketing_id}" size="60" class="input-large" />
    </div>
</div>

<div class="control-group">
    <label class="control-label">{__("newsman.remarketing_id_status")}:</label>
    <div class="controls">
        {if $newsman_remarketing_connected}
            <span style="display:inline-block;height:30px;line-height:30px;color:#28a745;"><strong>&#9679; {__("newsman.remarketing_id_valid")}</strong></span>
        {else}
            <span style="display:inline-block;height:30px;line-height:30px;color:#dc3545;"><strong>&#9679; {__("newsman.remarketing_id_invalid")}</strong></span>
        {/if}
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_remarketing_anonymize_ip">{__("newsman.remarketing_anonymize_ip")}:</label>
    <div class="controls">
        <input type="hidden" name="remarketing_anonymize_ip" value="N" />
        <input type="checkbox" name="remarketing_anonymize_ip" id="newsman_remarketing_anonymize_ip" value="Y" {if $newsman_settings.remarketing_anonymize_ip == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_remarketing_send_telephone">{__("newsman.remarketing_send_telephone")}:</label>
    <div class="controls">
        <input type="hidden" name="remarketing_send_telephone" value="N" />
        <input type="checkbox" name="remarketing_send_telephone" id="newsman_remarketing_send_telephone" value="Y" {if $newsman_settings.remarketing_send_telephone == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_theme_cart_compatibility">{__("newsman.theme_cart_compatibility")}:</label>
    <div class="controls">
        <input type="hidden" name="theme_cart_compatibility" value="N" />
        <input type="checkbox" name="theme_cart_compatibility" id="newsman_theme_cart_compatibility" value="Y" {if $newsman_settings.theme_cart_compatibility == "Y"}checked="checked"{/if} />
        <p class="muted description">{__("newsman.theme_cart_compatibility_help")}</p>
    </div>
</div>

{* === Developer Section === *}
<hr />
<h4>{__("newsman.developer_section")}</h4>

<div class="control-group">
    <label class="control-label" for="newsman_log_severity">{__("newsman.log_severity")}:</label>
    <div class="controls">
        <select name="log_severity" id="newsman_log_severity" class="input-medium">
            <option value="1" {if $newsman_settings.log_severity == 1}selected="selected"{/if}>{__("newsman.log_none")}</option>
            <option value="400" {if $newsman_settings.log_severity == 400}selected="selected"{/if}>{__("newsman.log_error")}</option>
            <option value="300" {if $newsman_settings.log_severity == 300}selected="selected"{/if}>{__("newsman.log_warning")}</option>
            <option value="250" {if $newsman_settings.log_severity == 250}selected="selected"{/if}>{__("newsman.log_notice")}</option>
            <option value="200" {if $newsman_settings.log_severity == 200}selected="selected"{/if}>{__("newsman.log_info")}</option>
            <option value="100" {if $newsman_settings.log_severity == 100}selected="selected"{/if}>{__("newsman.log_debug")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label cm-required" for="newsman_log_clean_days">{__("newsman.log_clean_days")}:</label>
    <div class="controls">
        <input type="number" name="log_clean_days" id="newsman_log_clean_days" value="{$newsman_settings.log_clean_days}" min="1" size="5" class="input-mini" />
    </div>
</div>

<div class="control-group">
    <label class="control-label cm-required" for="newsman_api_timeout">{__("newsman.api_timeout")}:</label>
    <div class="controls">
        <input type="number" name="api_timeout" id="newsman_api_timeout" value="{$newsman_settings.api_timeout}" min="5" size="5" class="input-mini" /> {__("newsman.seconds")}
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_dev_active_user_ip">{__("newsman.dev_active_user_ip")}:</label>
    <div class="controls">
        <input type="hidden" name="dev_active_user_ip" value="N" />
        <input type="checkbox" name="dev_active_user_ip" id="newsman_dev_active_user_ip" value="Y" {if $newsman_settings.dev_active_user_ip == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_dev_user_ip">{__("newsman.dev_user_ip")}:</label>
    <div class="controls">
        <input type="text" name="dev_user_ip" id="newsman_dev_user_ip" value="{$newsman_settings.dev_user_ip}" size="30" class="input-medium" />
    </div>
</div>

{* === Export Authorization Section === *}
<hr />
<h4>{__("newsman.export_auth_section")}</h4>

<div class="control-group">
    <label class="control-label cm-required">{__("newsman.authenticate_token")}:</label>
    <div class="controls">
        {if $newsman_settings.authenticate_token}
            <span class="unedited-element" style="font-family:monospace;font-size:14px;letter-spacing:0.5px;">*****{$newsman_settings.authenticate_token|substr:-2}</span>
        {else}
            <span class="muted">{__("newsman.token_not_set")}</span>
        {/if}
        <p class="muted description">{__("newsman.authenticate_token_help")}</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_export_auth_header_name">{__("newsman.export_auth_header_name")}:</label>
    <div class="controls">
        <input type="text" name="export_auth_header_name" id="newsman_export_auth_header_name" value="{$newsman_settings.export_auth_header_name}" size="40" class="input-medium" />
        <p class="muted description">{__("newsman.export_auth_header_name_help")}</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="newsman_export_auth_header_key">{__("newsman.export_auth_header_key")}:</label>
    <div class="controls">
        <input type="text" name="export_auth_header_key" id="newsman_export_auth_header_key" value="{$newsman_settings.export_auth_header_key}" size="40" class="input-medium" />
        <p class="muted description">{__("newsman.export_auth_header_key_help")}</p>
    </div>
</div>

{* === Buttons === *}
<div class="buttons-container">
    {include file="buttons/save.tpl" but_name="dispatch[newsman.save]"}
    <a href="{"newsman.oauth_login"|fn_url}" class="btn btn-warning" style="margin-left:20px;">{__("newsman.reconnect")}</a>
</div>
</form>

<script>
function newsmanFetchSegments(listId) {ldelim}
    var segSelect = document.getElementById('newsman_segment_id');
    segSelect.innerHTML = '<option value="">-- Loading... --</option>';

    if (!listId) {ldelim}
        segSelect.innerHTML = '<option value="">-- {__("newsman.no_segment")|escape:"javascript"} --</option>';
        return;
    {rdelim}

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '{"newsman.fetch_segments"|fn_url}' + '&list_id=' + encodeURIComponent(listId), true);
    xhr.onreadystatechange = function() {ldelim}
        if (xhr.readyState === 4 && xhr.status === 200) {ldelim}
            var segments = JSON.parse(xhr.responseText);
            var html = '<option value="">-- {__("newsman.no_segment")|escape:"javascript"} --</option>';
            for (var i = 0; i < segments.length; i++) {ldelim}
                html += '<option value="' + segments[i].segment_id + '">' + segments[i].segment_name + '</option>';
            {rdelim}
            segSelect.innerHTML = html;
        {rdelim}
    {rdelim};
    xhr.send();
{rdelim}
</script>
{/capture}

{include file="common/mainbox.tpl" title="{__("newsman.settings_title")}" content=$smarty.capture.mainbox}

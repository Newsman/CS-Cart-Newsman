{capture name="mainbox"}
<form action="{""|fn_url}" method="post" class="form-horizontal form-edit" name="newsman_oauth_save_form">
<input type="hidden" name="dispatch[newsman.oauth_save]" value="1" />
<input type="hidden" name="user_id" value="{$oauth_user_id}" />
<input type="hidden" name="api_key" value="{$oauth_api_key}" />

<div class="control-group">
    <label class="control-label" for="newsman_oauth_list_id">{__("newsman.list_id")}:</label>
    <div class="controls">
        <select name="list_id" id="newsman_oauth_list_id" class="input-medium" required>
            <option value="">-- {__("newsman.select_list")} --</option>
            {foreach from=$oauth_lists item=list}
                <option value="{$list.list_id}">{$list.list_name}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="buttons-container">
    {include file="buttons/save.tpl" but_name="dispatch[newsman.oauth_save]" but_text="{__('save')}"}
    <a href="{"newsman.oauth_login"|fn_url}" class="btn">{__("back")}</a>
</div>
</form>
{/capture}

{include file="common/mainbox.tpl" title="{__("newsman.oauth_select_list")}" content=$smarty.capture.mainbox}

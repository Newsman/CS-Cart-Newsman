{capture name="mainbox"}
<div class="well">
    <h4>{__("newsman.oauth_features_title")}</h4>
    <ul>
        <li>&#10003; {__("newsman.oauth_feature_sync")}</li>
        <li>&#10003; {__("newsman.oauth_feature_remarketing")}</li>
        <li>&#10003; {__("newsman.oauth_feature_forms")}</li>
        <li>&#10003; {__("newsman.oauth_feature_automation")}</li>
        <li>&#10003; {__("newsman.oauth_feature_products")}</li>
        <li>&#10003; {__("newsman.oauth_feature_orders")}</li>
    </ul>
    <div class="buttons-container">
        <a href="{$oauth_url}" class="btn btn-primary btn-large">{__("newsman.oauth_connect")}</a>
        {if $has_credentials}
            <a href="{"newsman.manage"|fn_url}" class="btn">{__("cancel")}</a>
        {/if}
    </div>
</div>
{/capture}

{include file="common/mainbox.tpl" title="{__("newsman.oauth_connect_title")}" content=$smarty.capture.mainbox}

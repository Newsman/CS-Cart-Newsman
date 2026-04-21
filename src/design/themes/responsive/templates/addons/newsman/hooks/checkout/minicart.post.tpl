{if $addons.newsman.remarketing_status == "Y" && $addons.newsman.theme_cart_compatibility != "Y"}
{$_nzm_cart = []}
{if $smarty.session.cart.products}
    {foreach from=$smarty.session.cart.products key="_nzm_key" item="_nzm_product"}
        {if !$_nzm_product.extra.parent}
            {$_nzm_cart[] = [
                "id"       => $_nzm_product.product_id|intval,
                "name"     => $_nzm_product.product|default:fn_get_product_name($_nzm_product.product_id),
                "price"    => $_nzm_product.display_price|default:$_nzm_product.price|floatval,
                "quantity" => $_nzm_product.amount|intval
            ]}
        {/if}
    {/foreach}
{/if}
<span data-newsman-cart="{$_nzm_cart|json_encode|escape:html}" style="display:none"></span>
{/if}

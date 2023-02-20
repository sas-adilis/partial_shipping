{if $can_create_partial_shipping || $partial_shipping}
    <div class="card mt-2">
        <div class="card-header">
            <div class="row align-items-center justify-content-between">
                <div class="col-auto">
                    <h3 class="card-header-title"><i class="material-icons">local_shipping</i> {l s='Manage multi shipping' mod='partial_shipping'}</h3>
                </div>
                {if $can_create_partial_shipping}
                    <div class="col-auto">
                        <a class="btn btn-sm btn-default" data-toggle="collapse" data-target="#collapsePartialShipping" href="#collapsePartialShipping" id="collapsePartialShippingButton">
                            {l s='Manage multi shipping' mod='partial_shipping'}
                        </a>
                    </div>
                {/if}
            </div>
        </div>
        <div class="card-body">
            {if $partial_shipping}
                <div class="alert alert-success" role="alert">{l s='This order was partially shipped out' mod='partial_shipping'}</div>
            {/if}
            {if $can_create_partial_shipping}
                <form action="{$form_action}" method="post" class="collapse" id="collapsePartialShipping">
                    <table class="table">
                        <thead>
                        <tr>
                            <th></th>
                            <th>{l s='Product' mod='partial_shipping'}</th>
                            <th class="text-center">{l s='Qty' mod='partial_shipping'}</th>
                            {if ($order->hasBeenPaid())}<th class="text-center">{l s='Refunded' mod='partial_shipping'}</th>{/if}
                            <th class="text-center">{l s='Qty shipped' mod='partial_shipping'}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        {foreach from=$products item=product key=k}
                            <tr>
                                <td>
                                    {if isset($product.image) && $product.image->id}{html_entity_decode($product.image_tag|escape:'htmlall':'UTF-8')}{/if}
                                </td>
                                <td>
                                    {$product['product_name']|escape:'htmlall':'UTF-8'}
                                    {if $product.product_reference}<br />{l s='Reference number:' mod='partial_shipping'} {$product.product_reference|escape:'htmlall':'UTF-8'}{/if}
                                    {if $product.product_supplier_reference}<br />{l s='Supplier reference:' mod='partial_shipping'} {$product.product_supplier_reference|escape:'htmlall':'UTF-8'}{/if}
                                </td>
                                <td class="text-center">
                                    <span class="product_quantity_show{if $product['product_quantity']|intval > 1} badge{/if}">{$product['product_quantity']|escape:'htmlall':'UTF-8'}</span>
                                </td>
                                {if ($order->hasBeenPaid())}
                                    <td class="productQuantity text-center">
                                        {$product['product_quantity_refunded']|escape:'htmlall':'UTF-8'}
                                        {if count($product['refund_history'])}
                                            <span class="tooltip">
                                        <span class="tooltip_label tooltip_button">+</span>
                                        <span class="tooltip_content">
                                        <span class="title">{l s='Refund history' mod='partial_shipping'}</span>
                                        {foreach $product['refund_history'] as $refund}
                                            {l s='%1s - %2s' sprintf=[{dateFormat date=$refund.date_add}, {displayPrice price=$refund.amount_tax_incl}] mod='partial_shipping'}<br />
                                        {/foreach}
                                        </span>
                                    </span>
                                        {/if}
                                    </td>
                                {/if}
                                <td>
                                    {if ($product['product_quantity'] - $product['product_quantity_refunded'] > 0)}
                                        <input type="text" class="form-control" id="quantity_shipped_{$product['id_order_detail']|escape:'htmlall':'UTF-8'}" name="quantity_shipped[{$product['id_order_detail']|escape:'htmlall':'UTF-8'}]" value="{($product['product_quantity']-$product['product_quantity_refunded'])|intval}" />
                                    {else}
                                        {($product['product_quantity']-$product['product_quantity_refunded'])|intval}
                                    {/if}
                                </td>
                                <td>
                                    {if ($product['product_quantity'] - $product['product_quantity_refunded'] > 0)}
                                        <input type="button" class="btn btn-outline-secondary btn-sm" value="{l s='Don\'t ship this product' mod='partial_shipping'}" onclick="$('#quantity_shipped_{$product["id_order_detail"]|intval}').val(0);" />
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                    <div class="row align-items-center mb-3">
                        <div class="col">
                            <div class="alert alert-warning mb-0">
                                {l s='If you choose multishipping option we create a new order with all product no shipped. This new order is associated to this order.' mod='partial_shipping'}
                            </div>
                        </div>
                        <div class="col">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="{l s='Tracking number' mod='partial_shipping'}" name="tracking_partial_shipping">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" name="submitPartialShipping" type="submit">{l s='Update shipping' mod='partial_shipping'}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            {/if}
        </div>
    </div>
{/if}
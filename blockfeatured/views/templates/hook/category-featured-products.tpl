{if !empty($categoryFeatured)}
    <section class="block-featured">
        {foreach from=$categoryFeatured item=subcategory}
        <div class='block block-featured-products block-featured-products-{$subcategory.id_category}'>
            <h2 class="title_block"><span>{$subcategory.name|escape:'html':'UTF-8'}</span> {l s='Featured Products'}</h2>
            {include file="$tpl_dir./product-list.tpl" products=$subcategory.products}
            <div class="text-right">
                <a href="{$link->getCategoryLink($subcategory.id_category, $subcategory.link_rewrite)|escape:'html':'UTF-8'}" title="{$subcategory.name|escape:'html':'UTF-8'}" 
                    class="btn btn-primary">
                    {l s='View all products in'} {$subcategory.name|escape:'html':'UTF-8'}
                </a>
            </div>
        </div>
        {/foreach}
    </section>
{/if}
{**
 * plugins/importexport/native/templates/export.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The export form for the native import/export plugin.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">
        {if $plugin->getDisplayName()}
        {$plugin->getDisplayName()|escape}
        {else}
        {$plugin->getName()|escape}
        {endif}
    </h1>
    <div class="app__contentPanel">
        <p>{$plugin->getDescription()|escape}</p>
        <p>{$plugin->getPluginInfo()}</p>

        {if $tabs}
            <script>
                $(function() {ldelim}
                    $('#importExportTabs').pkpHandler('$.pkp.controllers.TabsHandler');
                    {rdelim});
            </script>
            <div id="importExportTabs">
                <ul>
                    {foreach from=$tabs item=tab}
                        <li><a href="{$tab.url}">{$tab.name|escape}</a></li>
                    {/foreach}
                </ul>
                <div class="pkp_helpers_clear"></div>
            </div>
        {/if}

        {if $message}
            <div class="pkp_notification {$message.type}">
                {$message.message}
            </div>
        {/if}

        <form
                id="exportForm"
                class="pkp_form"
                action="{$plugin->getExportAction()}"
                method="post"
        >
            {csrf}

            {foreach from=$opts item=opt}
                {if $opt.type == 'radio'}
                    <fieldset class="pkp_form_options">
                        <legend>{$opt.label}</legend>
                        <div class="fields">
                            {foreach from=$opt.options item=option}
                                <div class="pkp_form_radio">
                                    <label>
                                        <input
                                                type="radio"
                                                name="{$opt.name}"
                                                value="{$option.value|escape}"
                                                {if $option.value == $opt.value}
                                                    checked
                                                {/if}
                                        >
                                        {$option.label}
                                    </label>
                                </div>
                            {/foreach}
                        </div>
                    </fieldset>
                {/if}
            {/foreach}

            {if $entities}
                {fbvFormArea id="entities" title=$entities.title description=$entities.description}
                    <div class="pkp_helpers_clear"></div>
                {if $entities.items->count() > 0}
                    <div id="entities-grid" class="pkp_grid pkp_grid_striped"
                         data-pkp-hanlder="Grid"
                    >
                        <div class="grid_body">
                            {iterate from=$entities.items item=entity}
                            {capture assign=rowId}row-{$entity->getId()}{/capture}
                                <div class="grid_row" id="{$rowId|escape}">
                                    <div class="row_fields">
                                        <div class="row_field pkp_form_checkbox">
                                            <input
                                                    type="checkbox"
                                                    name="selected[]"
                                                    value="{$entity->getId()|escape}"
                                            >
                                            {$entity->getLocalizedTitle()|escape}
                                        </div>
                                    </div>
                                </div>
                            {/iterate}
                        </div>
                    </div>
                {else}
                    <p>{translate key="common.noItemsFound"}</p>
                {/if}
                {/fbvFormArea}
            {/if}
            <div class="pkp_form_buttons">
                <button class="pkp_button pkp_button_primary" type="submit">
                    {translate key="common.export"}
                </button>
            </div>
        </form>
    </div>
{/block}
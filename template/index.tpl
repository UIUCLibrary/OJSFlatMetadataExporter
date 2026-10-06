{**
 * plugins/importexport/OJSFlatMetadataExporter/templates/index.tpl
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.importexport.OJSFlatMetadataExporter.displayName"}
	</h1>
	<div class="app__contentPanel">
		<p>{translate key="plugins.importexport.OJSFlatMetadataExporter.description"}</p>

		<form id="exportForm" class="pkp_form" action="{plugin_url path="export"}" method="post">
			{csrf}
			{fbvFormArea id="issuesForm"}
				<h3>{translate key="plugins.importexport.OJSFlatMetadataExporter.export.issues"}</h3>

				{if $issues|@count > 0}
					<p>{translate key="plugins.importexport.OJSFlatMetadataExporter.export.issues.description"}</p>

					<fieldset class="pkp_form_options">
						<legend class="pkp_screen_reader">{translate key="plugins.importexport.OJSFlatMetadataExporter.export.selectIssue"}</legend>
						<div class="fields">
							{foreach from=$issues item=issue}
								<div class="pkp_form_checkbox">
									<input
										type="checkbox"
										name="issueIds[]"
										id="issue-{$issue->id|escape}"
										value="{$issue->id|escape}"
										class="pkp_form_checkbox"
									>
									<label for="issue-{$issue->id|escape}">
										{$issue->title|escape}
									</label>
								</div>
							{/foreach}
						</div>
					</fieldset>

					<div class="pkp_form_buttons">
						<button class="pkp_button pkp_button_primary" type="submit">
							{translate key="plugins.importexport.OJSFlatMetadataExporter.export.export"}
						</button>
					</div>
				{else}
					<p>{translate key="common.noItemsFound"}</p>
				{/if}
			</fbvFormArea>
		</form>
	</div>
{/block}

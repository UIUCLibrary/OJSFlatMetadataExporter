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

			{if $issues->count() > 0}
				<p>{translate key="plugins.importexport.OJSFlatMetadataExporter.export.issues.description"}</p>

				{* This block is now modeled exactly on the official Native Export plugin's template *}
				{iterate from=$issues item=issue}
				{capture assign="checkboxId"}issue-{$issue->getId()}{/capture}
				{fbvElement type="checkbox" id=$checkboxId name="issueIds[]" value=$issue->getId() label=$issue->getLocalizedTitle()|escape}
				{/iterate}

				<div class="pkp_form_buttons">
					<button class="pkp_button pkp_button_primary" type="submit">
						{translate key="plugins.importexport.OJSFlatMetadataExporter.export.export"}
					</button>
				</div>
			{else}
				<p>{translate key="common.noItemsFound"}</p>
			{/if}
			{/fbvFormArea}
		</form>
	</div>
{/block}
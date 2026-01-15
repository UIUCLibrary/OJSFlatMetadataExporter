{**
 * plugins/importexport/OJSFlatMetadataExporter/templates/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * UI for the Flat Metadata Exporter plugin
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
			{fbvFormArea id="issuesXmlForm"}
				<h3>{translate key="plugins.importexport.OJSFlatMetadataExporter.export.issues"}</h3>
                <p>{translate key="plugins.importexport.OJSFlatMetadataExporter.export.issues.description"}</p>
				{iterate from=$issues item=issue}
					{fbvElement type="checkbox" id="selectedIssues[]" value=$issue->getId() label=$issue->getIssueIdentification()}
				{/iterate}
				{fbvFormButtons submitText="plugins.importexport.OJSFlatMetadataExporter.export.export"}
			{/fbvFormArea}
		</form>
	</div>
{/block}
{**
 * plugins/importexport/OJSFlatMetadataExporter/templates/index.tpl
 *
 * Lists the issues available for export.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle|escape}
	</h1>

	<p>{translate key="plugins.importexport.OJSFlatMetadataExporter.export.issues.description"}</p>

	<script type="text/javascript">
		$(function() {ldelim}
			$('#exportIssuesForm').pkpHandler('$.pkp.controllers.form.FormHandler');
		{rdelim});
	</script>
	<form id="exportIssuesForm" class="pkp_form" action="{plugin_url path="exportIssues"}" method="post">
		{csrf}
		{fbvFormArea id="issuesForm"}
			{capture assign=issuesListGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.issues.ExportableIssuesListGridHandler" op="fetchGrid" escape=false}{/capture}
			{load_url_in_div id="issuesListGridContainer" url=$issuesListGridUrl}
			{fbvFormButtons submitText="plugins.importexport.OJSFlatMetadataExporter.export.export" hideCancel="true"}
		{/fbvFormArea}
	</form>
{/block}

{**
 * plugins/importexport/OJSFlatMetadataExporter/templates/index.tpl
 *
 * This template's only job is to render the output prepared by the parent
 * NativeImportExportPlugin's display() method.
 *}
{extends file="plugins/importexport/native/templates/export.tpl"}

{block name="page"}
    {$output|unescape}
{/block}
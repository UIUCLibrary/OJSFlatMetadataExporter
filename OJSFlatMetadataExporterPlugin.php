<?php

namespace APP\plugins\importexport\OJSFlatMetadataExporter;

use APP\core\Application;
use APP\issue\Issue;
use PKP\plugins\importexport\native\PKPNativeImportExportPlugin;
use PKP\submission\Submission;

class OJSFlatMetadataExporterPlugin extends PKPNativeImportExportPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName()
    {
        return 'OJSFlatMetadataExporterPlugin';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.OJSFlatMetadataExporter.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.importexport.OJSFlatMetadataExporter.description');
    }

    /**
     * Display the plugin's management interface
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function display($args, $request)
    {
        // This is the exact pattern used by the NativeImportExportPlugin.
        // It delegates the entire list-building and display logic to the parent class.
        // We are telling it to list 'issue' entities.
        parent::listAll(
            $request,
            [
                'title' => __('plugins.importexport.OJSFlatMetadataExporter.export.issues'),
                'description' => __('plugins.importexport.OJSFlatMetadataExporter.export.issues.description'),
                'entities' => 'issue', // This tells the parent class what to fetch
            ]
        );
    }

    /**
     * @copydoc PKPNativeImportExportPlugin::getCLIExportResult
     */
    public function getCLIExportResult(string $command, array $cliArgs, string $workPath): ?string
    {
        // To be implemented later. Required by the parent class.
        return null;
    }

    /**
     * @copydoc PKPNativeImportExportPlugin::getExportResult
     */
    public function getExportResult(\PKP\core\PKPRequest $request, string $command, array $selectedIds, string $workPath): ?string
    {
        // To be implemented later. Required by the parent class.
        return null;
    }
}
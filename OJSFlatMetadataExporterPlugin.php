<?php

namespace APP\plugins\importexport\OJSFlatMetadataExporter;

use APP\plugins\importexport\native\NativeImportExportPlugin;

class OJSFlatMetadataExporterPlugin extends NativeImportExportPlugin
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
     * @copydoc ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
        // By calling the parent's display method, we inherit the entire working UI
        // from the NativeImportExportPlugin, including the tabs and lists for
        // articles and issues. This is the most stable approach.
        return parent::display($args, $request);
    }

    /**
     * Get the export result for a command-line export
     *
     * @param string $command
     * @param array $cliArgs
     * @param string $workPath
     * @return ?string Path to the created file or null
     */
    public function getCLIExportResult(string $command, array $cliArgs, string $workPath): ?string
    {
        // We will add our custom export logic here later. For now, it does nothing.
        // Returning null prevents the parent's XML export from running.
        return null;
    }

    /**
     * Get the export result for a web-based export
     *
     * @param \PKP\core\PKPRequest $request
     * @param string $command
     * @param array $selectedIds
     * @param string $workPath
     * @return ?string Path to the created file or null
     */
    public function getExportResult(\PKP\core\PKPRequest $request, string $command, array $selectedIds, string $workPath): ?string
    {
        // We will add our custom export logic here later. For now, it does nothing.
        // Returning null prevents the parent's XML export from running.
        return null;
    }
}
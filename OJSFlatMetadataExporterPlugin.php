<?php

namespace APP\plugins\importexport\OJSFlatMetadataExporter;

use APP\facades\Repo;
use PKP\plugins\ImportExportPlugin;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OJSFlatMetadataExporterPlugin extends ImportExportPlugin
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
     * @copydoc Plugin::display()
     */
    public function display($args, $request)
    {
        parent::display($args, $request);

        $context = $request->getContext();
        $templateMgr = \APP\template\TemplateManager::getManager($request);

        switch ($this->opType) {
            case 'export':
                // Logic for the export will be added here in our next step.
                // For now, it will just redirect back to the plugin page.
                $request->redirect(null, null, 'importexport', ['plugin', $this->getName()]);
                return;

            case null: // This is the default view when you load the plugin page.
                $issueCollector = Repo::issue()->getCollector()
                    ->filterByContextIds([$context->getId()])
                    ->filterByPublished(true)
                    ->orderBy('datePublished', 'desc');
                $issues = iterator_to_array($issueCollector->getMany());
                $templateMgr->assign('issues', $issues);
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                return;

            default:
                throw new NotFoundHttpException();
        }
    }

    /**
     * @copydoc ImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args)
    {
        // This plugin does not support command-line usage.
        $this->usage($scriptName);
    }

    /**
     * @copydoc ImportExportPlugin::usage()
     */
    public function usage($scriptName)
    {
        echo __("plugins.importexport.OJSFlatMetadataExporter.cliUsage", [
                'scriptName' => $scriptName
            ]) . "\n";
    }
}
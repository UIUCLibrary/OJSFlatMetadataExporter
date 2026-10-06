<?php

namespace APP\plugins\importexport\OJSFlatMetadataExporter;

use APP\core\Application;
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
        // This is the critical line that was missing. It explicitly tells the plugin
        // its own location, which is required for the TemplateManager to find templates.
        $this->setPath(dirname(__FILE__));
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
        $opType = $request->getRouter()->getRequestedOp($request);

        switch ($opType) {
            case 'export':
                $request->redirect(null, null, 'importexport', ['plugin', $this->getName()]);
                return;

            case 'index':
            case null:
                $issueCollector = Repo::issue()->getCollector()
                    ->filterByContextIds([$context->getId()])
                    ->filterByPublished(true)
                    ->orderBy('datePublished', 'desc');

                $issuesFromDb = $issueCollector->getMany();

                $issuesForTemplate = [];
                foreach ($issuesFromDb as $issue) {
                    $issuesForTemplate[] = (object) [
                        'id' => $issue->getId(),
                        'title' => $issue->getLocalizedTitle(),
                    ];
                }

                $templateMgr->assign('issues', $issuesForTemplate);
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

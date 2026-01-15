<?php

/**
 * @file plugins/importexport/ojsFlatMetadataExporter/OJSFlatMetadataExporterPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OJSFlatMetadataExporterPlugin
 * @ingroup plugins_importexport_ojsFlatMetadataExporter
 *
 * @brief OJS Flat Metadata Export plugin
 */

namespace APP\plugins\importexport\ojsFlatMetadataExporter;

use PKP\plugins\ImportExportPlugin;
use APP\template\TemplateManager;
use Symfony\Component\Process\Process;
use APP\facades\Repo;
use PKP\file\FileManager;
use PKP\services\PKPSchemaService;

class OJSFlatMetadataExporterPlugin extends ImportExportPlugin {
    /**
     * @see Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    /**
     * @see Plugin::getName()
     */
    public function getName() {
        return 'OJSFlatMetadataExporterPlugin';
    }

    /**
     * @see Plugin::getDisplayName()
     */
    public function getDisplayName() {
        return __('plugins.importexport.ojsFlatMetadataExporter.displayName');
    }

    /**
     * @see Plugin::getDescription()
     */
    public function getDescription() {
        return __('plugins.importexport.ojsFlatMetadataExporter.description');
    }

    /**
     * @see ImportExportPlugin::display()
     */
    public function display($args, $request) {
        parent::display($args, $request);
        $context = $request->getContext();

        switch ($this->opType) {
            case 'exportIssues':
                $issueIds = $request->getUserVar('selectedIssues');
                if (empty($issueIds)) {
                    // Redirect back with an error
                    $this->smarty->assign('error', 'plugins.importexport.ojsFlatMetadataExporter.export.noIssuesSelected');
                    return $this->smarty->fetch($this->getTemplateResource('index.tpl'));
                }

                // Get the issues and proceed to the export
                $this->exportIssues($issueIds, $context);
                break;
            default:
                // Display the export form
                $issueRepo = Repo::issue();
                $issueGalleys = $issueRepo->getMany([
                    'contextId' => $context->getId(),
                    'isPublished' => true,
                    'orderBy' => 'datePublished',
                    'orderDirection' => 'DESC',
                ]);

                $this->smarty->assign('issues', $issueGalleys);
                return $this->smarty->fetch($this->getTemplateResource('index.tpl'));
        }
    }

    /**
     * Perform the export.
     *
     * @param array $issueIds
     * @param \APP\journal\Journal $context
     */
    public function exportIssues($issueIds, $context) {
        $fileManager = new FileManager();
        $basePath = realpath($fileManager->getBasePath());
        $exportDir = $basePath . DIRECTORY_SEPARATOR . 'ojs-export-' . time();
        $fileManager->mkdir($exportDir);

        $issues = array_map(function($issueId) {
            return Repo::issue()->get($issueId);
        }, $issueIds);

        foreach ($issues as $issue) {
            if (!$issue || $issue->getJournalId() != $context->getId()) {
                continue;
            }

            // Create a directory for each issue
            $issueDirName = $this->_cleanFileName($context->getAcronym($context->getPrimaryLocale()) . '_' . $issue->getIdentification());
            $issuePath = $exportDir . DIRECTORY_SEPARATOR . $issueDirName;
            $fileManager->mkdir($issuePath);

            // Create galleys subdirectory
            $galleysPath = $issuePath . DIRECTORY_SEPARATOR . 'galleys';
            $fileManager->mkdir($galleysPath);

            // Prepare CSV file
            $csvFilePath = $issuePath . DIRECTORY_SEPARATOR . 'metadata.csv';
            $csvFp = fopen($csvFilePath, 'w');
            fputcsv($csvFp, $this->getCSVHeaders());

            $submissions = Repo::submission()->getMany([
                'contextId' => $context->getId(),
                'issueIds' => $issue->getId(),
                'status' => PKPSchemaService::STATUS_PUBLISHED,
            ]);

            foreach ($submissions as $submission) {
                // Write article metadata to CSV
                fputcsv($csvFp, $this->getArticleCSVRow($submission, $issue, $context));

                // Copy galleys
                $galleys = Repo::galley()->getMany(['publicationIds' => $submission->getCurrentPublication()->getId()]);
                foreach ($galleys as $galley) {
                    $submissionFile = Repo::submissionFile()->get($galley->getFileId());
                    if ($submissionFile) {
                        $sourcePath = $submissionFile->getData('path');
                        $destFileName = $this->_cleanFileName($submission->getId() . '_' . $galley->getId() . '_' . $submissionFile->getData('name'));
                        $fileManager->copyFile($sourcePath, $galleysPath . DIRECTORY_SEPARATOR . $destFileName);
                    }
                }
            }
            fclose($csvFp);
        }

        // Create a zip archive and stream it to the browser
        $archivePath = $exportDir . '.zip';
        $process = new Process(['zip', '-r', $archivePath, $exportDir]);
        $process->run();

        if ($process->isSuccessful()) {
            $fileManager->downloadFile($archivePath);
            $fileManager->removeDirectory($exportDir);
            $fileManager->deleteFile($archivePath);
        } else {
            // Handle error
            error_log('Failed to create zip archive: ' . $process->getErrorOutput());
            $fileManager->removeDirectory($exportDir);
        }
    }
    
    /**
     * Get the headers for the CSV file.
     * @return array
     */
    private function getCSVHeaders() {
        return [
            'galley_filename',
            'dc.title',
            'dc.creator',
            'dc.identifier.doi',
            'dc.subject',
            'dc.description.abstract',
            'dc.date.issued',
            'dc.language',
            'dc.genre',
            'dc.rights',
            'dc.type',
            'dc.relation.ispartof',
        ];
    }

    /**
     * Get a row for the CSV file for a single article.
     * @param \APP\submission\Submission $submission
     * @param \APP\issue\Issue $issue
     * @param \APP\journal\Journal $context
     * @return array
     */
    private function getArticleCSVRow($submission, $issue, $context) {
        $publication = $submission->getCurrentPublication();
        
        // Creators
        $creators = [];
        foreach ($publication->getData('authors') as $author) {
            $creators[] = $author->getFullName(false); // Format: First Last
        }

        // Subjects
        $subjects = (array) $publication->getData('keywords', $publication->getData('locale'));

        // Rights
        $rights = $publication->getData('licenseUrl');

        // Galley filenames
        $galleyFileNames = [];
        $galleys = Repo::galley()->getMany(['publicationIds' => $publication->getId()]);
        foreach ($galleys as $galley) {
            $submissionFile = Repo::submissionFile()->get($galley->getFileId());
             if ($submissionFile) {
                $galleyFileNames[] = $this->_cleanFileName($submission->getId() . '_' . $galley->getId() . '_' . $submissionFile->getData('name'));
            }
        }

        return [
            implode('||', $galleyFileNames),
            $publication->getData('title', $submission->getLocale()),
            implode('||', $creators),
            $publication->getData('doi'),
            implode('||', $subjects),
            strip_tags($publication->getData('abstract', $submission->getLocale())),
            $issue->getDatePublished(),
            $submission->getLocale(),
            Repo::section()->get($publication->getData('sectionId'))->getTitle($context->getPrimaryLocale()),
            $rights,
            'text',
            $context->getName($context->getPrimaryLocale()) . ', ' . $issue->getIdentification(),
        ];
    }

    /**
     * Clean a string to be used as a filename.
     * @param string $fileName
     * @return string
     */
    private function _cleanFileName($fileName) {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
    }

     /**
     * @copydoc PKPPlugin::getActions()
     */
    public function getActions($request, $verb) {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'importexport']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

     /**
     * @copydoc PKPPlugin::manage()
     */
    public function manage($args, $request) {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->registerPlugin('FormHandler', $this->getTemplateResource('formHandler.js'));

                $form = new SettingsForm($this, $context->getId());

                if ($request->isPost()) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }

                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * @copydoc ImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args) {
        // We don't have a CLI tool for this plugin.
        // Display the usage instructions.
        $this->usage($scriptName);
    }

    /**
     * @copydoc ImportExportPlugin::usage()
     */
    public function usage($scriptName) {
        echo "This plugin does not provide a command-line interface (CLI) tool.\n";
        echo "Please use the web-based interface from the Tools > Import/Export menu in OJS.\n";
    }
}
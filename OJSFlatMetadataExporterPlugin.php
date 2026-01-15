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

namespace APP\plugins\importexport\OJSFlatMetadataExporter;

use PKP\plugins\ImportExportPlugin;
use APP\template\TemplateManager;
use Symfony\Component\Process\Process;
use APP\facades\Repo;
use PKP\file\FileManager;
use PKP\services\PKPSchemaService;
use APP\core\Application;

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
        return __('plugins.importexport.OJSFlatMetadataExporter.displayName');
    }

    /**
     * @see Plugin::getDescription()
     */
    public function getDescription() {
        return __('plugins.importexport.OJSFlatMetadataExporter.description');
    }

    /**
     * @see ImportExportPlugin::display()
     */
    public function display($args, $request) {
        parent::display($args, $request);
        $context = $request->getContext();

        switch (array_shift($args)) {
            case 'export':
                $issueIds = $request->getUserVar('selectedIssues');
                if (empty($issueIds)) {
                    // This part is for user feedback, which we can improve later.
                    // For now, we assume valid selection.
                }
                $this->exportIssues($issueIds, $context, $request);
                break;
            default:
                // Display the export form
                $issueRepo = Repo::issue();
                $issues = $issueRepo->getMany([
                    'contextId' => $context->getId(),
                    'isPublished' => true,
                    'orderBy' => 'datePublished',
                    'orderDirection' => 'DESC',
                ]);

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign('issues', $issues);
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
        }
    }

    /**
     * Perform the export.
     *
     * @param array $issueIds
     * @param \APP\journal\Journal $context
     * @param \PKP\core\PKPRequest $request
     */
    public function exportIssues($issueIds, $context, $request) {
        $fileManager = new FileManager();
        $exportDir = $fileManager->getBasePath() . 'ojs-export-' . time();
        $fileManager->mkdir($exportDir);

        $issues = array_map(function($issueId) {
            return Repo::issue()->get($issueId);
        }, $issueIds);

        foreach ($issues as $issue) {
            if (!$issue || $issue->getJournalId() != $context->getId()) {
                continue;
            }

            $issueDirName = $this->_cleanFileName($context->getAcronym($context->getPrimaryLocale()) . '_' . $issue->getIssueIdentification());
            $issuePath = $exportDir . DIRECTORY_SEPARATOR . $issueDirName;
            $fileManager->mkdir($issuePath);

            $galleysPath = $issuePath . DIRECTORY_SEPARATOR . 'galleys';
            $fileManager->mkdir($galleysPath);

            $csvFilePath = $issuePath . DIRECTORY_SEPARATOR . 'metadata.csv';
            $csvFp = fopen($csvFilePath, 'w');
            fputcsv($csvFp, $this->getCSVHeaders());

            $submissions = Repo::submission()->getMany([
                'contextId' => $context->getId(),
                'issueIds' => $issue->getId(),
                'status' => 'published',
            ]);

            foreach ($submissions as $submission) {
                fputcsv($csvFp, $this->getArticleCSVRow($submission, $issue, $context));

                $galleys = Repo::galley()->getMany(['publicationIds' => $submission->getCurrentPublication()->getId()]);
                foreach ($galleys as $galley) {
                    $submissionFile = Repo::submissionFile()->get($galley->getFileId());
                    if ($submissionFile) {
                        $sourcePath = $submissionFile->getData('path');
                        $destFileName = $this->_cleanFileName($submission->getId() . '-' . $galley->getId() . '-' . $submissionFile->getData('name', $submissionFile->getLocale()));
                        $fileManager->copyFile($sourcePath, $galleysPath . DIRECTORY_SEPARATOR . $destFileName);
                    }
                }
            }
            fclose($csvFp);
        }

        $archivePath = $exportDir . '.zip';
        $process = new Process(['zip', '-r', $archivePath, $exportDir], $fileManager->getBasePath());
        $process->run();

        if ($process->isSuccessful()) {
            $fileManager->downloadFile($archivePath, null, true); // The `true` here should trigger the cleanup
            $fileManager->removeDirectory($exportDir);
            $fileManager->deleteFile($archivePath);
        } else {
            error_log('OJSFlatMetadataExporter Error: Failed to create zip archive. ' . $process->getErrorOutput());
            $fileManager->removeDirectory($exportDir);
        }
    }

    private function getCSVHeaders() {
        return [
            'galley_filename', 'dc.title', 'dc.creator', 'dc.identifier.doi',
            'dc.subject', 'dc.description.abstract', 'dc.date.issued', 'dc.language',
            'dc.genre', 'dc.rights', 'dc.type', 'dc.relation.ispartof',
        ];
    }

    private function getArticleCSVRow($submission, $issue, $context) {
        $publication = $submission->getCurrentPublication();

        $creators = [];
        $authors = $publication->getAuthors();
        foreach ($authors as $author) {
            $creators[] = $author->getFullName(false);
        }

        $subjects = $publication->getKeywords($publication->getLocale());

        $galleyFileNames = [];
        $galleys = Repo::galley()->getMany(['publicationIds' => $publication->getId()]);
        foreach ($galleys as $galley) {
            $submissionFile = Repo::submissionFile()->get($galley->getFileId());
             if ($submissionFile) {
                $galleyFileNames[] = $this->_cleanFileName($submission->getId() . '-' . $galley->getId() . '-' . $submissionFile->getData('name', $submissionFile->getLocale()));
            }
        }

        return [
            implode('||', $galleyFileNames),
            $publication->getLocalizedTitle($submission->getLocale()),
            implode('||', $creators),
            $publication->getDoi(),
            implode('||', $subjects),
            strip_tags($publication->getLocalizedAbstract($submission->getLocale())),
            $issue->getDatePublished(),
            $submission->getLocale(),
            Repo::section()->get($publication->getSectionId())->getLocalizedTitle(),
            $publication->getLicenseURL(),
            'text',
            $context->getLocalizedName() . ', ' . $issue->getIssueIdentification(),
        ];
    }

    private function _cleanFileName($fileName) {
        return preg_replace('/[^\w\-\.]/', '_', $fileName);
    }
}
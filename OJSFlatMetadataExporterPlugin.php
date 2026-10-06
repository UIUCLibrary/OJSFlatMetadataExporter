<?php

namespace APP\plugins\importexport\OJSFlatMetadataExporter;

use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\context\Context;
use PKP\file\FileManager;
use PKP\plugins\ImportExportPlugin;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

class OJSFlatMetadataExporterPlugin extends ImportExportPlugin
{
    /** Column headers of the exported metadata CSV */
    private const CSV_COLUMNS = [
        'issue_id',
        'issue_volume',
        'issue_number',
        'issue_year',
        'issue_title',
        'submission_id',
        'title',
        'authors',
        'abstract',
        'doi',
        'section',
        'date_published',
        'pages',
        'language',
        'files',
    ];

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName(): string
    {
        return 'OJSFlatMetadataExporterPlugin';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.importexport.OJSFlatMetadataExporter.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.importexport.OJSFlatMetadataExporter.description');
    }

    /**
     * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
     */
    public function getPluginSettingsPrefix(): string
    {
        return 'flatmetadata';
    }

    /**
     * @copydoc ImportExportPlugin::supportsCLI()
     */
    public function supportsCLI(): bool
    {
        return false;
    }

    /**
     * @copydoc ImportExportPlugin::display()
     *
     * @param array $args
     * @param Request $request
     */
    public function display($args, $request)
    {
        parent::display($args, $request);

        switch (array_shift($args)) {
            case 'index':
            case '':
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
            case 'exportIssues':
                $this->downloadExport((array) $request->getUserVar('selectedIssues'), $request->getContext());
                break;
            default:
                throw new NotFoundHttpException();
        }
    }

    /**
     * Build the export archive for the selected issues and send it to the browser.
     */
    protected function downloadExport(array $issueIds, Context $context): void
    {
        $issues = [];
        foreach ($issueIds as $issueId) {
            $issue = Repo::issue()->get((int) $issueId);
            if ($issue && $issue->getJournalId() == $context->getId()) {
                $issues[] = $issue;
            }
        }

        if (!$issues) {
            throw new NotFoundHttpException(__('plugins.importexport.OJSFlatMetadataExporter.export.noIssues'));
        }

        $exportFileName = $this->getExportFileName($this->getExportPath(), 'issues', $context, '.zip');
        try {
            $this->writeArchive($exportFileName, $issues, $context);
            $fileManager = new FileManager();
            $fileManager->downloadByPath($exportFileName);
        } finally {
            if (file_exists($exportFileName)) {
                unlink($exportFileName);
            }
        }
    }

    /**
     * Write a zip archive containing metadata.csv and the galley files of the given issues.
     *
     * @param \APP\issue\Issue[] $issues
     */
    protected function writeArchive(string $zipPath, array $issues, Context $context): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(__('plugins.importexport.OJSFlatMetadataExporter.export.zipError'));
        }

        $filesDir = rtrim(Config::getVar('files', 'files_dir'), '/') . '/';
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, self::CSV_COLUMNS);

        foreach ($issues as $issue) {
            $submissions = Repo::submission()
                ->getCollector()
                ->filterByIssueIds([$issue->getId()])
                ->filterByContextIds([$context->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
                ->getMany();

            foreach ($submissions as $submission) {
                $publication = $submission->getCurrentPublication();
                if (!$publication) {
                    continue;
                }

                $zipFileNames = [];
                foreach ($publication->getData('galleys') ?? [] as $galley) {
                    $submissionFileId = $galley->getData('submissionFileId');
                    $submissionFile = $submissionFileId ? Repo::submissionFile()->get($submissionFileId) : null;
                    $localPath = $submissionFile ? $filesDir . $submissionFile->getData('path') : null;
                    if (!$localPath || !is_file($localPath)) {
                        continue;
                    }
                    $extension = pathinfo($localPath, PATHINFO_EXTENSION);
                    $baseName = $this->sanitizeFileName(
                        $submissionFile->getLocalizedData('name') ?: ('galley-' . $galley->getId())
                    );
                    $baseName = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', $baseName);
                    $zipName = 'files/' . $submission->getId() . '/' . $galley->getId() . '-' . $baseName
                        . ($extension !== '' ? '.' . $extension : '');
                    if ($zip->addFile($localPath, $zipName)) {
                        $zipFileNames[] = $zipName;
                    }
                }

                $authors = [];
                foreach ($publication->getData('authors') ?? [] as $author) {
                    $authors[] = $author->getFullName(false);
                }
                $section = Repo::section()->get((int) $publication->getData('sectionId'));
                $doi = $publication->getData('doiObject');

                fputcsv($csv, [
                    $issue->getId(),
                    $issue->getVolume(),
                    $issue->getNumber(),
                    $issue->getYear(),
                    $issue->getLocalizedTitle(),
                    $submission->getId(),
                    $publication->getLocalizedFullTitle(),
                    implode('; ', $authors),
                    trim(strip_tags((string) $publication->getLocalizedData('abstract'))),
                    $doi ? $doi->getData('doi') : '',
                    $section ? $section->getLocalizedTitle() : '',
                    $publication->getData('datePublished'),
                    $publication->getData('pages'),
                    $publication->getData('locale'),
                    implode('; ', $zipFileNames),
                ]);
            }
        }

        rewind($csv);
        $zip->addFromString('metadata.csv', stream_get_contents($csv));
        fclose($csv);

        if (!$zip->close()) {
            throw new \RuntimeException(__('plugins.importexport.OJSFlatMetadataExporter.export.zipError'));
        }
    }

    /**
     * Reduce a name to characters that are safe inside a zip entry path.
     */
    protected function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name);
        $name = trim($name, '._');
        return $name !== '' ? $name : 'file';
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
        echo __('plugins.importexport.OJSFlatMetadataExporter.cliUnsupported') . "\n";
    }
}

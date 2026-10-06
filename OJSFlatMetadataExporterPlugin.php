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
    /** Column headers of the exported metadata CSV (Dublin Core, for IDEALS) */
    private const CSV_COLUMNS = [
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

    /** Separator between multiple values within a single CSV cell */
    private const VALUE_SEPARATOR = '||';

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
     * Write a zip archive containing, for each issue, a folder with metadata.csv and a galleys/ folder.
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
        $acronym = $context->getAcronym($context->getPrimaryLocale()) ?: $context->getPath();

        foreach ($issues as $issue) {
            $issueDir = $this->sanitizeFileName($acronym . '_' . $issue->getIssueIdentification());
            // Avoid collisions between issues that sanitize to the same name
            $issueDir .= '_' . $issue->getId();
            $zip->addEmptyDir($issueDir);
            $zip->addEmptyDir($issueDir . '/galleys');

            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, self::CSV_COLUMNS);

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
                $locale = $publication->getData('locale') ?: $submission->getData('locale');

                $galleyNames = [];
                foreach ($publication->getData('galleys') ?? [] as $galley) {
                    $submissionFileId = $galley->getData('submissionFileId');
                    $submissionFile = $submissionFileId ? Repo::submissionFile()->get($submissionFileId) : null;
                    $localPath = $submissionFile ? $filesDir . $submissionFile->getData('path') : null;
                    if (!$localPath || !is_file($localPath)) {
                        continue;
                    }
                    $extension = pathinfo($localPath, PATHINFO_EXTENSION);
                    $name = $this->sanitizeFileName(
                        $submissionFile->getLocalizedData('name') ?: ('galley-' . $galley->getId())
                    );
                    $name = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', $name);
                    $galleyName = $submission->getId() . '-' . $galley->getId() . '-' . $name
                        . ($extension !== '' ? '.' . $extension : '');
                    if ($zip->addFile($localPath, $issueDir . '/galleys/' . $galleyName)) {
                        $galleyNames[] = $galleyName;
                    }
                }

                $creators = [];
                foreach ($publication->getData('authors') ?? [] as $author) {
                    $creators[] = $author->getFullName(false);
                }
                $section = Repo::section()->get((int) $publication->getData('sectionId'));
                $doi = $publication->getData('doiObject');

                fputcsv($csv, [
                    implode(self::VALUE_SEPARATOR, $galleyNames),
                    $publication->getLocalizedTitle($locale),
                    implode(self::VALUE_SEPARATOR, $creators),
                    $doi ? $doi->getData('doi') : '',
                    implode(self::VALUE_SEPARATOR, $this->getKeywords($publication, $locale)),
                    trim(strip_tags((string) $publication->getLocalizedData('abstract', $locale))),
                    $issue->getDatePublished(),
                    $locale,
                    $section ? $section->getLocalizedTitle() : '',
                    $publication->getData('licenseUrl'),
                    'text',
                    $context->getLocalizedName() . ', ' . $issue->getIssueIdentification(),
                ]);
            }

            rewind($csv);
            $zip->addFromString($issueDir . '/metadata.csv', stream_get_contents($csv));
            fclose($csv);
        }

        if (!$zip->close()) {
            throw new \RuntimeException(__('plugins.importexport.OJSFlatMetadataExporter.export.zipError'));
        }
    }

    /**
     * Get the keywords of a publication in the given locale as plain strings.
     *
     * @return string[]
     */
    protected function getKeywords($publication, ?string $locale): array
    {
        $keywords = $publication->getData('keywords')[$locale] ?? [];
        $names = [];
        foreach ($keywords as $keyword) {
            $name = is_array($keyword) ? ($keyword['name'] ?? '') : (string) $keyword;
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names;
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

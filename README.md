# OJS Flat Metadata Exporter

An Import/Export plugin for [Open Journal Systems](https://pkp.sfu.ca/software/ojs/) (OJS) **3.5.x** that exports
the metadata of selected issues as a flat CSV file, together with the article galley files, in a single zip archive.
The layout is intended for easy bulk upload to repository systems such as IDEALS.

## Requirements

- OJS 3.5.x
- PHP `zip` extension (already required by OJS)

## Installation

1. Place this directory in your OJS installation as `plugins/importexport/OJSFlatMetadataExporter`
   (the directory name must match exactly):

   ```sh
   cd /path/to/ojs/plugins/importexport
   git clone https://github.com/UIUCLibrary/OJSFlatMetadataExporter.git OJSFlatMetadataExporter
   ```

2. Run the upgrade script so OJS registers the plugin:

   ```sh
   php tools/upgrade.php upgrade
   ```

## Usage

1. Log in as a journal manager or administrator.
2. Go to **Tools → Import/Export → Flat Metadata Exporter**.
3. Select one or more issues and click **Export**.
4. Your browser downloads a zip archive.

The plugin is web-only; command-line export is not supported.

## Output

The archive contains:

- `metadata.csv` – one row per published article in the selected issues, with the columns
  `issue_id`, `issue_volume`, `issue_number`, `issue_year`, `issue_title`, `submission_id`, `title`, `authors`,
  `abstract`, `doi`, `section`, `date_published`, `pages`, `language`, `files`.
  Multiple authors and files are separated by `; `.
- `files/<submission_id>/<galley_id>-<name>.<ext>` – the galley files of each article. The `files` column of
  `metadata.csv` lists the archive paths belonging to each row.

Only articles with a published status are exported.

## License

See the repository for license details.

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

The archive contains one folder per selected issue, named `<journal acronym>_<issue identification>_<issue id>`:

- `metadata.csv` – one row per published article, with Dublin Core columns:
  `galley_filename`, `dc.title`, `dc.creator`, `dc.identifier.doi`, `dc.subject`, `dc.description.abstract`,
  `dc.date.issued`, `dc.language`, `dc.genre` (the article's section), `dc.rights` (license URL), `dc.type`
  and `dc.relation.ispartof`. Multiple values within a cell (authors, keywords, galley files) are separated by `||`.
- `galleys/` – the galley files, named `<submission id>-<galley id>-<name>.<ext>`. The `galley_filename` column
  lists the files belonging to each row.

Only articles with a published status are exported.

## License

See the repository for license details.

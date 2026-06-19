# Rule: docmd docs sync

When a public command, config key, response object, retry invariant, or operational behavior changes in `src/`, update `docs-site/docs/` in the same change.

The sidebar in `docs-site/docmd.config.json` must include every Markdown page. Generated output stays ignored; commit source Markdown, config, assets, scripts, and `.docmd-search/config.json`.

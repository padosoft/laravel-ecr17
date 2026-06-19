# docmd Docs Skill

Use this skill when updating the `docs-site/` documentation site for `padosoft/laravel-ecr17`.

## Rules

- Keep docs as Markdown only. Do not add MDX, JSX, raw HTML, or `::: button`.
- Keep `docs-site/docmd.config.json` navigation synchronized with every page under `docs-site/docs/`.
- Prefer docmd containers: `callout`, `tabs`, `steps`, `collapsible`, `grids`, `grid`, and `card`.
- Include Mermaid diagrams and KaTeX where they clarify protocol, workflow, or data contracts.
- Keep semantic search configured through `.docmd-search/config.json` with `Xenova/all-MiniLM-L6-v2`.

## Verification

Run from `docs-site/`:

```bash
npm run check
npm run build
```

Then verify `_site/index.html`, `_site/llms.txt`, `_site/sitemap.xml`, and `_site/.docmd-search/manifest.json` exist.

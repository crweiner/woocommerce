# Contribution and implementation notes

Read before implementation and referenced throughout work on October 5, 2026.

## Sources and rules to retain

- [WooCommerce Contributing Guidelines](https://github.com/woocommerce/woocommerce/blob/trunk/.github/CONTRIBUTING.md): target trunk through a fork, use the repository build/lint workflow, meaningful regression tests, detailed testing instructions, and package change files. Do not manually change release changelogs or translation catalogs. Contributions use the project license; disclose security vulnerabilities privately through HackerOne.
- [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/), including the PHP, JavaScript, HTML, CSS, and accessibility chapters: tabs/LF, WordPress PHP names and Yoda checks, escaped output, documented public methods/hooks, localized UI text, valid native controls, visible keyboard focus, programmatic labels, and logical CSS where applicable. Keep existing legacy patterns where a framework rewrite is outside scope; new bulk interaction uses native DOM APIs.
- [WordPress Security](https://developer.wordpress.org/apis/security/): authorize separately from nonce verification, validate before mutations, sanitize with platform APIs, escape for the output context, and use trusted persistence/prepared reads. A nonce is not a permission check.
- [WooCommerce impact policy](https://github.com/woocommerce/woocommerce/blob/trunk/docs/contribution/contributing/deciding-pr-high-impact.md): new functionality, tax-data integrity, request handling, and SQL make this High-Impact. Apply `impact: high` and obtain independent human approval. Author/AI review cannot substitute.
- Source `AGENTS.md`, `.editorconfig`, `.npmrc`, `.nvmrc`, Prettier/ESLint/PHPCS configuration, and Studio `AGENTS.md`/`STUDIO.md` were inspected. Node24 is the configured runtime. Use `studio wp` for Studio; do not replace its WordPress core or SQLite drop-in.
- Backend/performance, testing, copy, review, Markdown, and commit skills were followed. Keep public contracts and tax IDs stable, reuse WC_Tax writes/hooks/cache, run both PHP lint commands, never add PHPStan baseline entries, and commit source/tests separately from change files.

## Kim's complete thread scope

The four replies and parent in the supplied Slack thread were read. Her Pennsylvania example has about 2,200 ZIP rates, with around 24,000 nationally. Requested outcomes are CSV updates instead of compulsory append/delete cycles, configurable rows via Screen Options, familiar row/select-all checkboxes, and bulk editing/removal including ZIP data. Explicit controls address the focus/password-manager frustration; actual password-manager testing remains for human QA.

## Design decisions

- Preserve default 100; native WordPress Screen Options bounds 1–999; per-user preference.
- Page selection and all-search-matches selection are distinct. Selection persists across pages and clears on search. Saved edits clear selection; failed saves retain edits.
- Blank bulk fields preserve current values; `*` removes geographic restrictions. ZIP strings retain zeroes and existing wildcard/range syntax.
- CSV matching uses country/state/class/priority and normalized ZIP/city sets. Rate/name/flags can change without creating a new identity. Updating ZIP/priority via CSV means a new identity, so use bulk editing to change those in place. Never overwrite an ambiguous match.
- Existing CSV columns and default append behavior stay intact. Existing rates update in place; no destructive table replacement or migration.
- JSON avoids input-variable truncation; legacy valid form requests remain supported. Reuse existing authorization, tax persistence and hooks. Preload the importer identity index with two reads rather than scanning every rate per CSV row.
- Full collection loading remains an existing limitation. At 24,000 rates the save worked locally in 11.55s but returned 7.89MB and used 120.5MB browser heap. Slow hosts still need assessment.

## Verification and limits

See PR.md for complete evidence and repeatable tests. Prototype patches were applied onto installed WooCommerce 11.1.2, retaining its surrounding code; complete source PHP was tested separately. Site snapshot and original plugin files were kept privately in `/tmp/woo-tax-before.sql` and `/tmp/woo-tax-plugin-before/`; neither belongs in public evidence.

PHP production analysis passes using the project's PHP 7.4 target. Tests pass under newer runtimes, which does not prove minimum-runtime execution. Test PHP files are outside the production PHPStan configuration and cannot be claimed clean with its unavailable test base classes. One existing customer-search test is skipped. Docker, multisite, minimum WordPress/PHP, extension integration, and actual password-manager behavior remain unverified.

No matching open PR was found; importer UTF-8 PR #66047 is a possible file conflict. Recheck overlap when opening the PR. This feature must receive independent human review before merge.

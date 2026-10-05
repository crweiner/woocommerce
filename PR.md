# Add bulk tax-rate editing, Screen Options, and CSV updates

## Pull request draft

### Submission Review Guidelines

- [x] I have followed the [WooCommerce Contributing Guidelines](https://github.com/woocommerce/woocommerce/blob/trunk/.github/CONTRIBUTING.md) and the [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/).
- [x] I have checked to ensure there aren't other open [Pull Requests](https://github.com/woocommerce/woocommerce/pulls) for the same update/change.
- [x] I have reviewed my code for [security best practices](https://developer.wordpress.org/apis/security/).
- [x] I have assessed the impact of this change and followed the applicable [review requirements](https://github.com/woocommerce/woocommerce/blob/trunk/docs/contribution/contributing/deciding-pr-high-impact.md#review-requirements).

### Changes proposed in this Pull Request

Managing 2,200 ZIP-based tax rates currently requires working through 22 pages. CSV imports always append, so correcting existing rates requires deleting and reimporting them. Add the tax-table controls requested by Kim:

- Persist the current user's table size through native Screen Options (1–999 rows; default 100).
- Provide keyboard-accessible row and page checkboxes, selection across pages, and an explicit “Select all matching rates” control. Searching clears selection.
- Bulk edit country, state, ZIP, city, rate, name, priority, compound, and shipping fields. Empty fields retain existing values; `*` clears location restrictions. Bulk removal includes selected rows on other pages. Changes remain staged until Save changes.
- Offer unchecked-by-default CSV updates. Match country, state, normalized ZIP/city sets, priority, and tax class; update unique matches in place and insert new locations. Preserve existing IDs and order. Skip ambiguous matches or invalid rows and report counts.
- Send large edits as an additive JSON request field to avoid PHP `max_input_vars` truncation. Preserve valid legacy array requests and show a retry message while retaining staged changes on save failure.

This is an enhancement; no bug-introducing PR is identified. Historical requests include [#2080](https://github.com/woocommerce/woocommerce/issues/2080) and [#16019](https://github.com/woocommerce/woocommerce/issues/16019). An October 5, 2026 open-PR search found no matching implementation. [#66047](https://github.com/woocommerce/woocommerce/pull/66047) touches importer UTF-8 conversion and may need conflict resolution.

**Impact and compatibility:** Apply `impact: high` and request independent human approval before merge. This adds merchant functionality and modifies request handling and tax-data writes. No existing public signatures, hooks, or registered asset handles are removed or renamed. CSV append remains the default; existing tax persistence methods, lifecycle hooks, and cache invalidation remain in use. Explicit checkboxes replace implicit focus/drag row selection. CSV update mode cannot identify a changed location or priority as the old row: it inserts a new identity; use bulk editing to change those fields in place. Ambiguous duplicate identities are skipped rather than overwritten.

Reads use the current site's table prefix and native user-option APIs. No database migration is needed. Multisite and minimum supported runtime versions were not executed and need reviewer/CI validation. Existing order-tax metadata behavior is outside this feature; see [#30570](https://github.com/woocommerce/woocommerce/issues/30570).

### Screenshots or screen recordings

| Before | After |
| ------ | ----- |
| ![100-row tax table without explicit selection](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/before-tax-table.png) | ![Checkboxes and bulk actions on the tax table](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-tax-table.png) |
| ![CSV importer always appends](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/before-csv-import.png) | ![Opt-in CSV update control](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-csv-import.png) |

![Native per-user Screen Options](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-screen-options.png)

![Bulk editing across 2,200 rates](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-bulk-editor.png)

![ZIP changes saved for selected rows](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-zip-bulk-edit.png)

![CSV updates one existing row without inserting another](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-csv-update-result.png)

![24,000-row scale demonstration](https://raw.githubusercontent.com/crweiner/woocommerce/codex/tax-rate-bulk-editing-evidence/screenshots/after-24000-rates.png)

### How to test the changes in this Pull Request

Use a test store with tax calculation enabled and synthetic rates. Do not use live tax data.

1. Build classic assets with `pnpm --filter=@woocommerce/classic-assets build`. In WooCommerce → Settings → Tax, create a dedicated tax class and enough ZIP-specific rates to span several pages.
2. Open its rate table. Use Screen Options to choose 1, then 500 rows. Apply and reload; confirm the setting persists. Tax options should not offer the rate-table preference.
3. Focus a row checkbox and press Space. Check the header checkbox and confirm only the current page is selected. Navigate between pages; selection should persist. Choose Select all matching rates and check the total.
4. Choose Edit → Apply. Set ZIP to `00501; 00504`, rate to `6.5`, and shipping to No. Leave other fields empty. Update selected rates, then Save changes. Reload and confirm only selected rows changed and leading zeroes remain. Use `*` to clear a location restriction.
5. Search for a narrow match; confirm selection clears and pagination resets. Select all matching rates, choose Remove → Apply, then Save changes. Confirm matching rates across pages are removed and other rates/classes remain.
6. Export a small CSV, change its rate/name, and import with Update existing rates checked. Confirm it reports updates without duplicate IDs. Repeat without checking it; confirm the original append behavior. Test reordered semicolon-separated locations, wildcard/range ZIPs, ambiguous duplicates, and invalid rates/priorities/classes.
7. Repeat all-matching bulk edits with 2,200 and 24,000 rates. Verify database row counts, selected changes, and unchanged ZIP restrictions. A failed save should retain staged edits and allow retry.
8. Run the targeted PHP suites and `settings-tax.spec.ts` browser regression. Run required changed-file/branch lint and PHPStan before merge.

### Testing that has already taken place

- Official repository PHPUnit suite against isolated MariaDB 13.0.2 and WordPress 7.0, PHP 8.5.9, PHPUnit 9.6.35: 167 tests, 2,751 assertions, one pre-existing skipped customer-search test. Includes 25 importer cases, 17 Screen Options cases, and AJAX legacy/JSON/authorization/invalid-batch coverage. Earlier targeted tax-only run: 75 tests, 2,380 assertions.
- New committed Playwright regression passes on Studio, including persistent page size, keyboard selection, ZIP leading zeroes, unselected rows, preserved IDs, all-page edits/removal, and failed-save retry. Existing deletion tests now use explicit checkboxes.
- Studio prototype: WordPress 7.1.2, installed WooCommerce 11.1.2 with this feature's isolated patches and rebuilt classic assets, PHP 8.4, SQLite, debug enabled. The full 11.3 source was used for PHP tests. Studio is a feature prototype, not a full trunk installation.
- Browser workflow passes for 2,200 rates, persisted ZIP/shipping changes, search selection, staged deletion, and opt-in CSV update. No page errors on the successful workflow. Desktop/mobile checks confirm bulk controls wrap and keyboard focus remains visible.
- Separate 24,000-rate demo: all 24,000 name edits persisted; ZIP restrictions and 6% rates remained intact. Selection took 0.14s, save plus render 11.55s. Response was 7.89MB; used browser heap after save was about 120.5MB. The existing table still loads its full class collection, so slower hosts and larger datasets need additional performance testing.
- Changed-file and full-branch PHP/JS lint, new-test PHPCS, classic CSS lint/build, PHPStan on all modified production PHP, and Markdown lint pass without code findings. Five resolved importer baseline entries were removed; none added. The production PHPStan configuration excludes test infrastructure; an exploratory test-file run reported unavailable PHPUnit/WordPress test base classes and is not a clean test-file static-analysis result.
- Security review checked nonce/capability protection, whole-batch shape validation before writes, escaping, site-scoped SQL reads, ambiguous-match handling, and existing persistence/hooks/cache behavior. AI review supplements the required independent human review.
- Not executed: full Docker suite (Docker unavailable), full monorepo end-to-end suite, multisite, minimum supported PHP/WordPress, extension integration, production load testing, or real password-manager interaction.

### Milestone

<!-- milestone-target-selection -->
- [x] Automatically assign milestone for the **[next WooCommerce version](../blob/trunk/plugins/woocommerce/woocommerce.php#L6)**
<!-- /milestone-target-selection -->

> **Note:** Check the box above to have the milestone automatically assigned when merged.
> Alternatively (e.g. for point releases), manually assign the appropriate milestone.

### Changelog entry

Created `plugins/woocommerce/changelog/tax-rate-bulk-editing` with the changelog CLI (minor/enhancement). Added the corresponding classic-assets package entry manually because that private package has no changelog script.

- [ ] Automatically create a changelog entry from the details below.
- [ ] This Pull Request does not require a changelog entry. (Comment required below)

Merchant-facing entry: Manage tax rates with bulk editing, checkbox selection, Screen Options, and CSV updates to existing rates.

### Use of AI Tools

OpenAI Codex authored the implementation, regression tests, and this draft, using up to two parallel agents for importer work, research, review, and scale testing. The human submitter must review the generated changes and take responsibility for the contribution. No AI review satisfies the required independent human approval.

# Release candidate checklist — 2026-10-15

Current status: **Needs improvement**, not certified for production. This supersedes earlier 2026-10-14 results. Local QA data preserved; migrations additive after clone/restore verification.

| Item | Fix / evidence | Status |
|---|---|---|
| Frontend dependencies P1 | Mix removed, modern Webpack, PostCSS overrides, runtime Vue, sanitized Markdown and JSON, date-picker removed. npm 50→4 packages; high17→1. `docs/audit/FRONTEND_SECURITY_RC2.md`; fresh npm ci/build/audit. | Exploit paths reviewed/mitigated; upstream risk remains |
| Word AI P1 | TranslateText unique queued job; source/target/hash/provider revision, bounded retries/cooldown; word polling with selection/input guards. WordTranslationQueueTest verifies no inference in HTTP, duplicate cache reuse, personal meaning unchanged, stale target409. | Ready for tested queue flow |
| Cold/concurrency P1 | Real Ollama unload certified by empty model listing; two concurrent HTTP requests and two workers create one job. `rc2-ai-cold-concurrency.json`: cold98.72s, cache9877ms under8GB memory pressure. | Needs improvement: latency under load |
| Idiom/editorial P1 | Actual model still translates “No tires la toalla” literally. Needs_review flag for known failure, approval wins. Published fixture uses “Don't give up”; browser visible approved wording. | Reviewed fixture corrected; model quality not universally certified |
| Catalogue/CMS P1 | Original ordered chapters, immutable revisions/publications and snapshots; old enrollments stay on old version; draft separate; approve/revoke hash and audit. Full publication translation badge requires all chapters. StoryPublicationTest passes including revoke. | Ready for tested model |
| Browser core P2 | Real signup→onboard→selected story→two chapters→save panadería→review Good→progress; reload preserves personal meaning/schedule. Stale pair tab rejects save. | Ready for observed flow |
| Resume P2 | Fixed actual #reader-content scrolling; restored800px after reload, source hash owner API tests. Finish recorded238 words/1 completion/1 review. | Ready for observed flow |
| Import P1/P2 | UI discovered missing processing method in options event; fixed server-required detailed value, display422 errors, retain text for retry. Real plaintext→Python→default queue→book11→reader11 succeeds. Screenshots rc2-import-success.jpg / rc2-import-reader.jpg. | Plaintext verified; file uploads Experimental |
| Upload UI | Filechooser timeout; second direct chooser blocked. Native Codex control explicitly denied by safety tool. No bypass or successful browser file upload claimed. API/parser EPUB/SRT results remain distinct. | Blocked in current browser tools |
| Interface locale P2 | Independent user.locale/session middleware + Blade/Vue catalogs. Menu vi/en independent from es→en/vi; browser verified target change retains English menus. | Mechanism Ready; full copy translation incomplete |
| Active time P2 | Focus/visible/recent interaction heartbeat, server elapsed cap, multitab dedup, local midnight/TZ split; no scroll completion. InterfaceLocaleAndHeartbeatTest passed; actual UI4 active minutes. | Ready for tested cases |
| Session/offline P2 | Axios419/401 and network/restored warning events, retained input. Executable frontend event tests pass. | Automated logic Ready; complete live failure matrix pending |
| Recovery P1 | Broadcast unavailable no longer fails processed chapter; POST+CSRF owner retry idempotent; chapter polling returns actual counts and retains data on errors. ChapterProcessingRecoveryTest passed. | Ready for tested cases |
| Backend/Python | Fresh `DB_DATABASE=holatales_unit_20261014 php -d error_reporting=24575 artisan test`:65 tests351 assertions; Python6 pass, Pint check, Composer audit0. | Ready for these checks |
| Build | Clean npm ci / production build; 12 legacy scoped-style export warnings. No lint/typecheck configured. | Builds; warning cleanup pending |
| Visual matrix | Prior landing320/375/768/1280, saved review/progress and mobile reader375 evidence. Current viewport API calls returned innerWidth1280 despite requested sizes; rc2-reader-final-* images are desktop, must not count as responsive proof. Landscape/dark/focus/all-page final matrix incomplete. | Needs improvement/tool limitation |
| Repeatable E2E/CI | Playwright config and disposable credential suite added; not executed here. CI strict audit remains failing on remaining alerts; no hosted CI result. | Needs improvement |
| Backup/restore | Before new migrations QA cloned:37 tablecounts14FK; prior populated unit restore retained. New migrations applied QA without reset. Final new-schema QA restore:44 table counts/29FK pass (`rc2-restore-final.log`). | Ready for performed drill; new-schema drill completed |
| Secrets/runtime | No production deploy/payment/external personal AI fallback. Historical real credential rotation cannot be verified locally. PHP8.2 container and full sustained load not run. | External/runtime gates pending |

Next priorities: maintained frontend migration; complete controlled browser upload/visual/failure matrix; editorial fixture coverage and AI capacity; finish interface copy catalogs; execute hosted CI against an isolated environment.

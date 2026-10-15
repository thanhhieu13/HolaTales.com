# HolaTales — BA / QA audit, 14 October 2026

## Main finding and change

Previously, only eight curated glossary entries in the first demo story had Vietnamese definitions. There was no whole-story translation screen or default fallback for missing definitions. This was a functional gap, not evidence of a broken translation provider.

Implemented automatic English → Vietnamese translation for free previews and curated story chapters via MyMemory, with a 30-day cache, bounded request segments, timeouts and quota/response validation. The reader shows loading, error/retry and hide/show controls. Live word check: selecting “young” fills “trẻ tuổi” in the translation field. Selecting an untranslated English word from a curated story fetches a definition; asynchronous responses cannot replace another selected word or an edited meaning. Existing saved translations are preserved. Private/imported chapter text is not sent externally until the learner explicitly agrees in the product. Translation is currently for text chapters up to 5,000 characters; longer content receives a clear validation error.

Live verification: The Little Bakery returns an actual Vietnamese machine translation. Machine wording is imperfect (for example gender/pronouns), so it is labeled as a reference translation. This is not a verified editorial translation.

Also fixed: invalid importType now returns 422; DeepL upstream failure/missing quota fields no longer becomes a successful response containing NaN; asynchronous vocabulary fields now follow the store's translation value; translation panel moved inside reader content, after the original text, to avoid sidebar overlap and text movement during asynchronous translation loading.

## Scope and test matrix

| Area | Checked | Outcome |
|---|---|---|
| Landing / free preview / premium preview | HTTP and previous browser journey; free 200, premium preview 404 | Pass |
| Signup / onboarding / public role injection | Regression tests and previous real browser registration | Pass |
| Login / logout / password recovery / signed email verification | Existing regression suite, prior login/logout UI | Pass at test level; real mail delivery blocked |
| Catalogue / search / level filter / no results | HTTP pages: A2 200, unsupported Z9 422, no-match 200 | Pass for checked requests |
| Story enrolment / duplicate start / private book | Regression tests | Pass |
| Reader / glossary / translation / privacy | Live browser/API, translation regression tests | Pass for curated English text; privacy and provider failure tests |
| Save vocabulary / Vietnamese paste / review scheduling | Previous live learning journey; auto-word translation checked this iteration | Pass for checked journey |
| Progress / goals / calendar | Page/API smoke and actual 66-word journey | Pass; aggregate semantics still need harmonization |
| Learner access to admin/editor | 403 on both pages, managed user creation 403 | Pass |
| Administrator modules | /admin + users/languages/dictionaries/fonts/api/reviews/editor: 200; supporting reads 200 | Render/API smoke pass; not all mutations tested |
| DeepL quota | Unconfigured provider request fails; handled as failure, not NaN | Configuration blocked; actual valid-key integration untested |
| Invalid import type | API request returns 422 after fix | Pass |
| Cross-user chapter access | API denies data but returns 500 | Functional isolation holds; error contract fails |
| Default font download | 200 | Pass |
| Health endpoint | /up 200 | Basic web health only; no queue/tokenizer readiness guarantee |
| Backup / restore | Source review only, no dump/restore performed | Risks below; not runtime pass |
| EPUB / subtitles / YouTube / website import | Request/source inspection; plain-text import previously tested | Other import modes not end-to-end verified |
| Anki / Jellyfin / LibreTranslate / TTS sound | Config endpoints read; no connected services/audio listening | Blocked / not verified |
| Mobile | New library previously checked at 390×844; translated reader and hide/show control checked at 390×844 | Screenshot evidence; physical-device/browser matrix outstanding |

API logs: qa-results/ba-learner-smoke.log and qa-results/ba-admin-smoke.log. Regression logs: qa-results/translation-tests.log. Initial test-fixture failures were repaired (legacy models require unique_words, kanji and lemma); they were test setup failures, not product failures.

## Remaining backlog, prioritized

| ID | Priority / evidence | Trigger or finding | Direction to fix / acceptance |
|---|---|---|---|
| BA-01 | P0 / code review | Website import accepts any valid URL and Python downloads it; no private-IP/redirect restriction seen | Central URL allowlist or DNS/IP validation, block private/loopback/link-local and redirect hops; regression tests; do not allow SaaS users to query internal services |
| BA-02 | P0 / known repository history | .env is removed from current tracking but remains in history | Operator audit and rotation of real leaked secrets; clean history only with coordination; verify production secrets never appear in image/build context |
| BA-03 | P1 / reproduced | /chapters/get/reader with a different user's chapterId returns 500 instead of 403/404 | Use a domain exception or findOrFail plus preserve HTTP errors in controllers; expect 404 without data or stack trace in production |
| BA-04 | P1 / code review | Only importType was tightened; import body/file limits and EPUB/subtitle MIME validation remain incomplete | Type-specific FormRequest rules, maximum size/character limits, sanitized parser, safe shared file paths, failure cleanup and transactional import; corrupted/oversized files rejected before processing |
| BA-05 | P1 / code review | Backup removes old files before verifying dump; fixed Docker path and shell-concatenated credentials; zero/missing retention may loop | Process argv/credentials file, Storage path, positive retention, atomic temp dump; prune only after verified success; restore drill |
| BA-06 | P1 / code review | Signup saves user before sending SMTP notification outside transaction; mail failure can interrupt successful signup | Queue mail after commit with retry; redirect learner successfully and show resend status; email failure must not strand a created account |
| BA-07 | P1 / code review | User implements verification but learning routes do not require verified email; no SaaS resend/verification screen | Decide release verification policy; add verification screen/resend UX and route enforcement consistent with demo access; verify pending account cannot reach restricted actions |
| BA-08 | P1 / code review | Password change does not require current password; change max32 while signup/login allow72 | Re-authenticate with current password, align bounds, rotate sessions as chosen; test malicious/expired session and recovery route |
| BA-09 | P1 / partial runtime | Queue processing fallback works but /up only proves web health; worker/tokenizer down may leave users waiting | Queue-aware readiness, observable jobs, bounded waiting, retry controls, idempotent jobs, poll with backoff; test kill/restart/tokenizer 503 |
| BA-10 | P1 / release gap | Free translation service shared quota; per-user throttle exists but no provider-wide character budget/circuit breaker | Choose production provider, shared quota/budget, background preparation, rate limit by user and provider; never cache error text as a translation |
| BA-11 | P1 / current limitation | Whole-story translation limited to 5,000 chars, text/English only | Add queued paragraph translation with progress and resume for long stories; subtitle support and target-language options require explicit separate scope |
| BA-12 | P1 / code review | Existing review correct/again requests lack robust visible failure recovery and can advance UI before requests settle | Await all related saves; disable duplicate clicks; present retry without losing card; network failure must not falsely report completion |
| BA-13 | P1 / code review | Dashboard historical words = current chapter word_count × read_count; calendar uses goal achievements, so editing a previously read chapter can change historical totals | Use immutable reading/review event ledger, derive all aggregates from it, transactionally update daily goals; test edits/deletions/timezone boundaries |
| BA-14 | P2 / confirmed design gap | Catalogue has only three demos; editor can create drafts/toggle publication but cannot edit body/glossary or manage revisions | Full editorial CRUD, preview, reviewed translations/glossaries, level/word count validation and version history; no seeder overwrites editorial changes |
| BA-15 | P2 / visual review | Reader/review/settings still legacy UI and mixed English/Vietnamese labels | Unify navigation, labels and controls with SaaS discovery/dashboard, accessible error states and keyboard/mobile checks |
| BA-16 | P2 / workflow gap | Auto-fill words and whole-story translation are separate; no highlighted sentence-by-sentence bilingual reading | Add bilingual paragraph mode and contextual definitions, preserve original text, allow hide translation; assess learning behavior before replacing existing reader |
| BA-17 | P2 / release gap | Private content shows consent before external translation but SaaS lacks full privacy/export/delete-account flow | Document providers/retention; data export and account deletion with explicit confirmation; legal content requires operator-approved wording |
| BA-18 | P2 / build warnings | Host PHP8.4 produces dependency deprecations; Vue2/Bootstrap/Sass warnings remain | PHP8.2/8.3 deployment baseline or tested dependency upgrade; stage frontend migration after regression coverage |
| BA-19 | Blocked | Billing and subscription downgrade/cancellation not implemented | Keep checkout unavailable; implement provider webhooks, retries, entitlement lifecycle and quotas only after provider/pricing decision |
| BA-20 | Blocked | No actual production container, HTTPS/WSS, mail delivery, load/concurrency or restore test | Staging release checklist and measured load tests before opening paid accounts |

Evidence distinguishes reproduced errors, source-review risks and unverified integrations. Passing page HTTP status does not establish that every action inside that page works. This report does not claim the entire product is production-ready.

## Suggested work batches

1. Security/import/credentials: BA-01–04. Acceptance: guest/learner/other-user negative cases all return appropriate responses; unsafe URLs/files never reach parsers.
2. Operational reliability/auth: BA-05–09 and BA-12. Acceptance: mail/provider/queue/save failures are recoverable and never falsely show success.
3. Translation and measurement: BA-10–13, BA-16. Acceptance: long story translations resume, budget is bounded, and historical progress remains stable after edits.
4. Product polish/editor/privacy: BA-14–18. Acceptance: editors can revise and preview; learners get one coherent desktop/mobile flow.
5. Staging and monetization: BA-19–20 after infrastructure and payment decisions.

Prompt: "Read docs/BA-TEST-REPORT-2026-10-14.md. Implement batch 1 first, preserve existing learning behavior and per-user ownership, add meaningful regression tests and a reproduction-to-fix table. Continue with batch 2 only after batch 1 passes. Do not deploy production, rotate external credentials, enable billing, or pretend blocked integrations pass. Report tests and remaining risks."

Final regression result: 23 tests, 82 assertions, all pass; 18 existing PHP dependency deprecation notices remain.

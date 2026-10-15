# HolaTales learning SaaS — implementation and acceptance

This first runnable stage serves individuals learning English (A1, A2, B1), with Vietnamese glossary definitions. It reuses the existing reader, word/phrase ownership checks, review scheduler and goals. Payment is deliberately not enabled.

## Stages delivered

1. Security foundation: public signup cannot assign role/plan; `/users/create` now requires administrator even on an empty database. The first admin is promoted using a server-side Artisan command after normal registration. There is no public bootstrap race. `.env` is untracked, `.env.example` is a template, `.dockerignore` excludes credentials and local data. Compose builds this repository instead of upstream images and keeps Redis/tokenizer unexposed; web/Reverb ports bind localhost for a reverse proxy.
2. Public discovery: landing, a free interactive sample, registration and onboarding.
3. Learning: a curated three-story catalogue, filtering, per-user story enrolment and background preparation, existing reader/glossary, vocabulary editing, daily reviews, dashboard progress and streak.
4. Roles and plans: editor drafts and publication, admin user management, server-side Free/Pro story access, a clearly unavailable Pro checkout page.

## Schema

| Entity | Fields and invariants |
|---|---|
| users additions | role learner/editor/admin; is_admin kept compatible with existing admin middleware; plan free/pro; learning_level A1/A2/B1; translation_language vi; daily_word_goal; onboarded_at |
| stories | unique slug, title, English body, level, topic, summary, glossary JSON, is_premium, published_at; unpublished stories excluded from learner catalogue |
| story_enrollments | user_id + story_id unique; private book_id; cascade on deletion; start serializes on user row and reuses existing copy |
| existing books/chapters | Each learner receives their own copy; existing read_count and processing_status retained |
| existing encountered_words/phrases/goals/goal_achievements | Scoped by authenticated user; glossary fills only empty translations and never overwrites learner notes |

## Route and screen map

| Route | Screen/action | Access |
|---|---|---|
| GET / | Landing | Public |
| GET /try/{story} | Interactive sample with safe text-only meanings | Published Free only |
| GET/POST /register | Signup, automatic login, onboarding redirect | Guest; POST rate-limited |
| GET/POST /onboarding | Language, level, translation language, daily reading goal | Authenticated |
| GET /learn | Recommendations, continue reading, current goals | Authenticated; onboarding required |
| GET /stories | Curated story catalogue, search and level filter | Authenticated; onboarding required |
| POST /stories/{story}/start | Enrolment and private copy | Authenticated; server checks published/language/plan |
| GET /learn/books/{book} | Polling preparation screen or redirect to reader | Owner only; 404 otherwise |
| GET /chapters/read/{chapter} | Existing reader | Existing authenticated owner-scoped API |
| GET /vocabulary/search | Existing vocabulary, editing/import/export | Authenticated, scoped data |
| GET /review/false/-1/-1 | Due reviews | Authenticated, scoped data |
| GET /progress | Reading totals, today’s target, saved/due words, completed chapters, streak | Authenticated, scoped data |
| GET /plans | Free/Pro information, checkout unavailable | Authenticated |
| GET/POST /editor/stories | Editorial list and create draft | Editor or admin only |
| POST /editor/stories/{story}/publish | Toggle publication | Editor or admin only |
| POST /users/create, existing /admin APIs | Managed accounts/configuration | Admin only |
| GET/POST /forgot-password, GET/POST /reset-password | Password recovery | Guest, throttled; local mail writes to log |
| GET signed /verify-email/{id}/{hash} | Verification | Authenticated, signed URL |

## Acceptance and evidence

- All PHPUnit tests pass: 17 tests / 60 assertions on isolated `holatales_unit_20261014`; 18 dependency deprecation notices remain under host PHP 8.4. Use PHP 8.2/8.3 for the locked dependencies. Log: qa-results/saas-tests.log.
- Fresh npm ci and frontend production build pass with a pinned Webpack and root package-lock; Docker Node stage uses npm ci. Log: qa-results/saas-build.log.
- Browser: new learner signs up; chooses English/A1/100 words; starts The Little Bakery; background worker processes chapter; clicks bakery and receives Vietnamese meaning; pastes an expanded meaning; marks level 7; completes reading. Dashboard shows 66/100 words, one completed chapter, one saved/due word and a one-day streak.
- Browser: due review reveals the saved meaning; correct answer moves bakery from -7 to -6 and next review to tomorrow. Dashboard due count becomes zero.
- Mobile: curated library checked at 390×844; navigation wraps, cards stack and inputs remain usable. Screenshot: qa-results/saas-library-mobile.jpg.
- Browser: Free learner attempting The Last Train is redirected to /plans; no checkout and no premium copy created.
- Tests: guest and learner cannot create managed admin accounts; public signup ignores malicious role/plan fields; editor can publish but cannot manage users; repeated story start does not duplicate books/chapters; another learner cannot open the private enrolment; onboarding rejects unsupported languages.
- Sample meanings use textContent and escaped Blade text, avoiding arbitrary HTML injection. Review answer is aria-hidden before reveal. Input events cover paste/autofill better than keyup-only fields.

## Run locally

Use PHP 8.2/8.3, Composer, Node, MySQL, Redis and the Python tokenizer with en_core_web_sm. Keep a dedicated database. Copy `.env.example` to `.env` only on a fresh checkout, set credentials and generate an APP_KEY. Install Composer dependencies, `npm ci`, `npm run production`, `php artisan migrate`, `php artisan db:seed`, run `php artisan serve` and `php artisan queue:work`. Reverb is optional for preparation screen polling; configure matching server/client keys for existing live reader updates.

After registering the intended operator account, run `php artisan holatales:role operator@example.com admin`; `editor` assigns editorial access. This changes a real role, so use only the intended account. The CLI prevents removing the last admin. No public API can edit plan.

Existing local QA login: qa@example.test / HolaQA2026! (admin). New learning-flow login: learner-saas@example.test / HolaLearn2026! (learner Free). Both are disposable local fixtures, never production credentials.

## Before commercial release

- Content: expand and proofread stories/glossaries; current three stories are original demo text. Editorial UI creates drafts and toggles publication; full revision management/glossary editing is still a later stage.
- Email: local mail uses log; delivery infrastructure, verified-email release policy, deliverability and account anti-abuse must be configured and tested on staging. Do not claim actual email delivery from unit tests.
- Billing: choose provider, currency, pricing and quotas; implement checkout/webhooks/entitlement lifecycle before enabling Pro sales. No public upgrade endpoint or fake payment exists.
- Security: removing `.env` does not remove it from Git history. Inspect/rotate real historical credentials with the operator. Production compose and HTTPS/WSS need real infrastructure validation; Docker daemon was unavailable in this session.
- Operations: address remaining readiness backlog (SSRF-safe URL import, upload/import validation, robust retries, backups/restore drills, dependency upgrades, monitoring, load tests). Do not deploy the MVP as production-ready merely because the learning flow passes.
- UI: reader/review remain the legacy Vue/Vuetify interface with SaaS navigation; the new discovery/dashboard screens use server-rendered Blade. A complete visual unification is a separate iteration.
- Production migration adds a named MySQL volume instead of mounting the source `database/` folder. Existing installs must backup and restore explicitly; do not switch an existing deployment without migration planning.

## Automatic translation update

Reader and free preview now request English–Vietnamese machine translation via MyMemory and cache successful results. Curated unknown words also get automatic definitions. Private imported chapters require explicit in-product consent before an external request. English text chapters up to 5,000 characters are supported; longer translation, quota budgeting and production provider selection remain outstanding. See docs/BA-TEST-REPORT-2026-10-14.md for the latest audit and 23-test result.

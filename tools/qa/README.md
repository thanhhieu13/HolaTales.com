# Release candidate verification

All commands are local. Test database: `holatales_unit_20261014` only. Never set the test DB to the browser database or a personal/production database. PHPUnit and the scripts acquire the same exclusive process lock and fail if another QA run is using the disposable DB.

1. `DB_DATABASE=holatales_unit_20261014 php -d error_reporting=24575 artisan test`
2. `npm ci && npm run production`
3. `vendor/bin/pint --test`
4. `.qa-venv/bin/python -m unittest discover -s tools/tests -v`
5. `composer audit --format=json` and `npm audit --json` (advisories must be evaluated; do not ignore security checks).
6. After tests finish, run `DB_DATABASE=holatales_unit_20261014 REDIS_PREFIX=holatales_rc_isolated_ TRANSLATION_PROVIDER=ollama TRANSLATION_BACKGROUND=true QUEUE_CONNECTION=redis php -d error_reporting=24575 tools/qa/live_translation.php`. This creates public synthetic fixtures only, on the disposable test DB. Worker/cache isolation is essential. Read the JSON outputs and manually assess fidelity, fluency, completeness. A worker start is not a quality pass.
7. Generate import fixtures with `.qa-venv/bin/python tools/qa/generate_import_fixtures.py`, then run `DB_DATABASE=holatales_unit_20261014 php tools/qa/live_import.php`. Only after previous DB work finishes. Live parser/synchronous processing is separate from browser upload and real queue transport.
8. After all test/live processes finish: `php tools/qa/restore_drill.php`. The script reads only the named test DB and creates `holatales_restore_20261014`. It refuses an existing target. For another drill use `RC_DRILL_TARGET=holatales_restore_new_name`. Sources are restricted to the named unit/QA DB; optionally `RC_DRILL_SOURCE=holatales_qa_20261014` creates a safe inspection clone. Restored DB is retained for inspection. Never restore into the application database.

Translations require a shared cache for unique jobs, Redis and `php artisan queue:work translations --queue=translations --timeout=240 --tries=2`. Retry-after is 300 seconds, job timeout 240 seconds, bounded provider HTTP timeout at most 210 seconds. A worker for the `default` queue does not process translations. Never process isolated QA jobs with a worker connected to the browser or production database.

Reading position migration is additive; test it in the disposable DB first. Rollback only a disposable DB after checking its migration batch. In a deployed environment prefer rolling back application code and retaining the additive tables; their down methods delete reading-position/audit history.

Lint/typecheck: not configured. Browser smoke and viewport coverage remain separate gates from feature tests. No CI browser suite is configured yet.

CI check workflow is configured but not run here. Its frontend audit fails while known npm advisories remain; no continue-on-error suppresses that release gate.

## Repeatable browser runner (2026-10-15)
`E2E_BASE_URL=http://127.0.0.1:8000 E2E_EMAIL=<disposable-user> E2E_PASSWORD=<local-password> npm run test:browser`.
The server, Python tokenizer and default queue worker must already run against a disposable QA DB. The runner never migrates/resets data; plaintext imports add synthetic books. Install Chromium with the official Playwright CLI in the CI environment. This suite has been added but has not been executed in this desktop session; local UI evidence uses Codex browser tools. It is not a substitute for the signup/save/review/resume and upload matrix.

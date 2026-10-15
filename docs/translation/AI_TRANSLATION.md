## Update 2026-10-15

Word translation now uses TranslateText queue with hash/pair/provider version uniqueness and bounded polling. No reader HTTP fallback inference. Local cold/concurrency evidence: `qa-results/rc2-ai-cold-concurrency.json`; two requests/two workers deduplicate to one job. Cold98.72s/cache9877ms are observations on8GB hardware. Literary-v3 still mistranslates the known idiom; needs_review flag is narrow, not general quality detection. Approved original-chapter translations reuse exact source hashes in preview/enrolled chapters and can be revoked without losing history. `OLLAMA_KEEP_ALIVE` defaults30s to reduce resident model memory. Do not run browser/build/load measurements concurrently and interpret them as isolated model latency.

# AI translation — local pilot

Research: 2026-10-14. Chosen pilot: Qwen3.5:4b via existing Ollama local server. No API key or per-request API charge; local compute/storage costs remain. Machine has8GB unified memory, so9B+ is not selected. Model download approximately3.3GB. Sources: https://ollama.com/library/qwen3.5 and https://docs.ollama.com/api/chat . Google Gemini Flash free tier also exists (https://ai.google.dev/gemini-api/docs/pricing), with quota/API-key requirements and free-tier content-use policy; not enabled here.

## Configuration

```
ollama pull qwen3.5:4b
TRANSLATION_PROVIDER=ollama
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_TRANSLATION_MODEL=qwen3.5:4b
TRANSLATION_TIMEOUT=180
```

Keep Ollama on localhost. Docker web may use `http://host.docker.internal:11434` only with appropriate host reachability; container deployment has not been verified. Do not publish11434 publicly. No model, server URL or secret is accepted from the learner request. `.env` is ignored and server-side config only.

## Behavior

AI receives the entire bounded chapter (up to5000characters), source/target and a system instruction for faithful literary translation. Temperature0.1, thinking disabled, structured JSON paragraph array with exact count, paragraph/dialogue/name/number preservation and Spanish subject/pronoun consistency. Source text is treated as data, including any embedded instructions. We do not use AI-generated confidence as proof of quality.

Available output must be valid JSON containing nonempty translation, within30000characters. Incomplete generation, malformed response, HTTP/network failure are rejected; failure status/cooldown is stored. No automatic external fallback. MyMemory remains an explicit configuration option.

Provider/model/prompt revision identifies generated cache and content rows. An unreviewed older-provider record is regenerated lazily; archived source content, learner meanings and schedules are not erased. Editorial-approved translations remain authoritative, and late machine responses cannot overwrite them. Local private chapters do not need external-upload consent; MyMemory retains that consent requirement.

Tests: request full context, cache/provider isolation, persistent status, no external fallback on failure, truncation rejection and editorial priority. Live quality/latency results are in `qa-results/ai-translation-live.json` and `ai-story-live.json`. Earlier cold-start failures are retained in `ai-translation-cold.json`. Small model output remains unreviewed and requires editorial assessment before release; no universal quality claim is made.

## Verified local results

- Ollama0.34.2, qwen3.5:4b Q4_K_M downloaded and selected in ignored local `.env`.
- Three warm fixtures: pronouns25.02s, negation/numbers/time24.77s, idiom9.46s. Source Lucía/Ana→she/her; siete y cuarto→seven fifteen; paragraph boundaries retained. An isolated omitted subject remains ambiguous (model chooses “he”); no universal quality certification.
- Real published Spanish story La panadería de Lucía→English:21.39s generated,0.007s cached in service, correct she/her for Lucía and he/him for the child. Provider is `Ollama:qwen3.5:4b:literary-v2`, status unreviewed. Editorial translations still win.
- Earlier cold start exceeded90s, and an earlier prompt mistranslated7:15 as7:45. Prompt/schema and sampling were revised, context reduced4096, repetition penalties disabled, current timeout180s, model stays warm5m. Cold-load behavior still depends on available RAM; this is a local pilot, not an SLA.
-46 tests passed,1deprecated,217assertions after final configuration; production build successful. The AI-specific tests use mocked HTTP; the fixtures above are real model outputs.
- Browser visual verification was blocked by the browser tool URL security policy. No UI pass or AI screenshot is claimed. Open `/try/4` manually to see the saved AI translation.

Rollback: set `TRANSLATION_PROVIDER=mymemory` and run `php artisan config:clear`. Older-provider unreviewed translations regenerate lazily; do not clear user vocabulary, schedules or approved editorial content. Keep Ollama available for new local translations. No cloud account/key, paid inference, production deployment or destructive migration was used.


## RC background increment — 2026-10-14

Chapter/story reader requests enqueue by default (`TRANSLATION_BACKGROUND=true`), with queued/processing/available/failed/unavailable states, bounded UI polling and cooldown. Run `php artisan queue:work translations --queue=translations --timeout=240 --tries=2`; Horizon has a single translation supervisor. Shared cache unique lock and Redis are required. Sync queue configuration returns unavailable instead of running chapter/story inference in the web request. Word inference remains synchronous and is an open item. PHPUnit explicitly sets background=false for historical mock integration cases; `BackgroundTranslationTest` enables it and asserts real enqueue/worker semantics with queue/HTTP fakes.

Live Redis worker evidence: `qa-results/rc-live-translation.json` and `rc-live-worker-detail.log`. Agent quality review found correct pronouns/negation/numbers/time/names in these synthetic samples, but “No tires la toalla” was too literal (“Don't throw away the towel”); use editorial “Don't give up”. Output remains unreviewed. A260s timed-out initial attempt and interrupted retry are retained as failures/invalidated attempts, not overwritten with historical success. No cold SLA or perfect quality is claimed.

Long fixture:12 ordered synthetic paragraphs completed108.44s, queued request59.98ms and cached request4177.90ms (`rc-live-translation-long.json`). Agent review checked all day numbers1–12, names, negation, two vs three books,7:15 and paragraph boundaries. Repeated synthetic prose is a limited completeness fixture, not a broad literary benchmark. Slow cached lookup remains a performance issue to measure under controlled load.

# Current implementation increment — 2026-10-15

**Needs improvement; full RC not certified.** Current evidence is in [RELEASE_CANDIDATE_CHECKLIST](../product/RELEASE_CANDIDATE_CHECKLIST.md) and [frontend exploit-path review](FRONTEND_SECURITY_RC2.md). Earlier results below are historical.

Implemented all-word background translation and pair guard; normalized ordered original chapters, immutable publication revisions and editorial approval/revoke; independent interface locale, active heartbeat with midnight/multitab rules; actual reader-panel scroll resume; optional realtime failure recovery and idempotent owner POST retry. Replaced Mix with Webpack, sanitized JSON/Markdown, removed vulnerable date-picker use and upgraded nested build parsers. Fresh npm audit:4 affected packages,1high/3low (from50/17high), Composer0. Upstream Vue2/Vuetify2 advisories remain mitigated, not patched.

Fresh verification:65 PHP tests/351 assertions,4 executable frontend tests,6 Python tests, Pint check, npm ci and production build. Build retains12 scoped-style export warnings. Lint/typecheck Not configured. Real browser signup→selected story→onboarding→reader→save personal meaning→review→progress verified; target change/stale tab guard and800px panel resume verified. Plaintext import failed initially because the options event erased required detailed processing method; fixed, then real UI→Python→default queue→reader succeeded, book11/chapter11. Images: qa-results/rc2-import-success.jpg, rc2-import-reader.jpg, rc2-review-saved-meaning.jpg, rc2-reader-restored.jpg, rc2-reader-mobile-editorial.jpg.

Cold local Ollama concurrency:2 simultaneous requests/2workers produce1 job.98.72s cold and9877ms cache observed under8GB memory pressure. Known idiom output remains too literal; flagged for review, editorial fixture approved as “Don't give up” and rendered correctly. No inference quality/SLA claim. Translation worker is running on local QA. Browser filechooser timeout and explicit native-app safety denial prevent claiming upload E2E; formats remain Experimental. Viewport override API returned1280px despite requested widths in final recheck; latest filename dimensions are not responsive proof. Full visual/dark/failure matrix, complete locale copy and hosted E2E execution remain open. Playwright suite/config added but not locally executed.

No production deploy, payment, external personal-content fallback, QA reset, secret rotation or history cleanup was performed. QA accounts and existing data remain. Final new-schema restore drill passed44 table counts and29 foreign keys; disposable clone retained (`rc2-restore-final.log`). Worker lock order aligned user→chapter with HTTP pair/heartbeat mutations. Book/chapter unique word counts now exclude skipped punctuation consistently.

---

# Current release-candidate increment — 2026-10-14

**Status: Needs improvement; release candidate not certified.** Current evidence supersedes the historical pass counts below. Full scope/status/risk table: [RELEASE_CANDIDATE_CHECKLIST](../product/RELEASE_CANDIDATE_CHECKLIST.md).

Implemented: background story/chapter translation with dedicated Redis worker, bounded polling/retries/cooldown and stale-source guard; strict stale-pair vocabulary mutation guard with user locking; duplicate phrase prevention and initial SRS scheduling; bounded EPUB ZIP validation and removal of personal-content debug logging; durable reading-position API keyed by source hash; editorial approval revoke/audit; selected story preserved through registration/onboarding; additive migrations tested then applied to cloned-backed local QA; repeatable populated disposable restore drill; isolated QA process lock and CI check configuration.

Security dependency update: Laravel12.70 (from11.15), current compatible Composer dependencies, direct Axios1.20; PHP8.2.28 resolver platform and actual local PHP8.4 check. Vue2/Mix remain. Whole-repo Pint formatting creates a broad legacy formatting diff while preserving pre-existing work. No production deploy, billing, destructive user DB operation or external AI fallback.

Current tests: **59 passed,286 assertions**, production build succeeds, Pint passes, six Python security tests pass; lint/typecheck Not configured. Live isolated worker completed es→en pronoun, negation/time/number/name and two-paragraph fixtures in9.51–30.29s; enqueue26.69–81.79ms. The idiom fixture is too literal and stays unreviewed. A preceding worker attempt exceeded260s; its interrupted retry is not counted as a pass. An additional12-paragraph live fixture completed108.44s and retained all paragraph/day-number/name/quantity/time content; its cached observation was4177.90ms. Cold-start certification remains open; do not infer fast cached latency from smaller fixtures. Live plaintext/EPUB/SRT parse + synchronous processing succeeded, without browser upload or real import queue transport certification.

Populated restore drill:37 table counts and14FK checks pass, restored disposable DB retained. Browser QA pre-migration clone:35 tables/10FK pass. Final browser matrix is blocked by browser timeout/unavailability; desktop catalogue screenshot predates final reader changes. Composer security advisory state and npm50 advisory backlog are in the checklist; frontend/normalized content/all-AI concurrency/locale/browser gates prevent claiming RC readiness.

---

# Historical report (retained, not current verification)

# HolaTales — Final implementation and verification report

Ngày: 2026-10-14. Trạng thái: **bản local có thể chạy, đã cải thiện đáng kể; chưa production-ready**. Đây là báo cáo cho các giai đoạn đã triển khai trong workspace, không phải chứng nhận mọi yêu cầu đã hoàn thành. Không deploy hoặc tích hợp thanh toán thật. Không truy cập/xóa database production, không xóa migration cũ. Những thay đổi hiện chưa commit/push.

## A. System Overview

Giữ Laravel11/MySQL cho API, auth/session, quyền và nghiệp vụ; Vue2/Vuetify/Vuex/Router cho reader, review, vocabulary, import và admin cũ; Blade cho public, onboarding, catalogue, dashboard/progress và editorial. Mix/Webpack build frontend. Python Bottle/spaCy xử lý NLP/import; queue/Redis/Reverb cập nhật processing. Cần chạy tokenizer và queue ngoài web server.

Module mới: `LearningController` quản lý learner/catalogue/editor; `LearningTranslationController` kiểm tra ownership/consent và dịch; `LearningTranslationService` provider/cache/quota/error; `ContentTranslationService` liên kết bản dịch với phiên bản truyện/chương; `TranslationPreferenceService` lưu/khôi phục nghĩa và SRS theo target; `FlashcardReviewService` submit nguyên tử; `LearningClock` ngày học theo account; `SafeWebsiteService` fetch URL công khai có giới hạn.

Local: [Homepage](http://127.0.0.1:8000), [Dashboard](http://127.0.0.1:8000/learn). Tài khoản learner fixture: `learner-saas@example.test`, mật khẩu `HolaLearn2026!`. Đây là fixture local, không dùng như tài khoản production.

## B. Issues Found

Danh sách chi tiết Severity, vị trí, nguyên nhân, tác động và xử lý: `FULL_SYSTEM_AUDIT.md` (A01–A26); source map: `SOURCE_INVENTORY.md`. Những vấn đề nổi bật:

- Critical: URL import SSRF, quản lý/bootstrap quyền chưa phù hợp SaaS, env tracked.
- High: finish chapter bị cộng lặp; nghĩa/lịch ôn trộn target; import có thể tạo dữ liệu một phần; backup shell/retention; subtitle HTML; review cập nhật qua nhiều request; dependency cũ.
- Medium: shell/mobile không nhất quán, lỗi paste form, timezone, thiếu versioned content translations, bộ lọc/catalogue/CMS chưa đầy đủ.
- Low: SEO/style debt, backup GET mutation, frontend thử nghiệm không active.

Phát hiện thêm qua visual QA: hướng dẫn reader che nút Focus Mode; bốn nút review chồng lên card ở320px; directive Blade dính nhau làm trang không render. Đã sửa và bổ sung regression render/queue tests.

## C. Fixes Implemented

|Nhóm|Các file chính|Thay đổi và lý do|
|---|---|---|
|Auth/roles|`UserService`, auth controllers/requests, `User`, auth/web/console routes, SaaS register/onboarding|Public user không tự nâng quyền; backend admin/editor checks; CLI bootstrap; reset/verify routes; yêu cầu password hiện tại; thông báo expired session; registration không bị strand bởi lỗi mail.|
|Data isolation|Book/Chapter/Vocabulary services/controllers, `LearningTranslationController`|Owner-scoped resources,404 khi không thuộc user; không dịch chapter riêng khi chưa consent; ngôn ngữ target/source được validate.|
|Translation|`LearningTranslationService`, `ContentTranslationService`, editor controller/views, migrations000002/3/6|Persistent cache pair/hash, shared cache lock, timeout/quota handling, statuses, content FK/version hash; CMS duyệt bản dịch; không ghi đè approval khi provider hoàn thành muộn; source/target độc lập.|
|Vocabulary/SRS|`TranslationPreferenceService`, migration000004, TextBlockGroup/Vocabulary panels|Lưu/khôi phục nghĩa và schedule từng target; glossary chỉ đúng target; auto lookup có loading/error và guard selection; không overwrite nghĩa đã nhập.|
|Review|`FlashcardReviewService`, `ReviewController`, Review.vue, migration000005|Again/Hard/Good/Easy, UUID replay protection, state/pair validation, user/card locks, transaction stage+history+goal; lỗi giữ thẻ. Practice không lưu. Không cộng reading words từ review client nữa.|
|Progress/timezone|`ChapterService`, `LearningController`, `LearningClock`, Goal/EncounteredWord/Phrase/ReviewService, migrations000003/7|Completion snapshot unique, chỉ cộng một lần; số từ thật từ tokenizer; daily dates theo timezone account; activity14ngày từ events. Lịch cũ không rewrite.|
|Import/security|Import service/controller/requests, `SafeWebsiteService`, tokenizer.py, Import UI|Input/file bounds, HTML-only URL fetch public IP pinned/no redirect; tokenizer không tự tải URL và chỉ đọc file trong temp root; local bind127.0.0.1; atomic chapters/jobs afterCommit; paste/keyboard accessibility; nguồn chưa kiểm thử ghi Experimental.|
|Backup/config|`BackupService`, `.gitignore`, `.env.example`, `.dockerignore`, compose/Dockerfile|Process argv thay shell, temp credentials0600, dump hợp lệ trước prune; env không vào Git/build context; không expose Redis/tokenizer; DB volume; cấu hình bắt buộc.|
|Build/maintenance|package.json/lock, `public/learning-design.css`, changed PHP files|Pin Webpack5.97.1, Moment2.31.0, build lockfile; Pint format changed PHP; shared tokens/focus/reduced-motion.|

Test nguồn: các Feature tests SaaS/translation/multilingual/security/content translation/review/timezone và auth; Python path-security test. Không thay review intervals cũ; grades mới dùng `setStage` lịch đang có: Again theo logic relearning cũ, Hard giữ stage, Good+1, Easy+2 tối đa0. Đây là mở rộng thuật toán hiện có, không tuyên bố FSRS/SM-2 chuẩn.

## D. UI/UX Improvements

- Public: navigation ngang đủ Home/HowItWorks/Stories/Features/Pricing/FAQ/Login/CTA, hero lấy truyện publish thật, pair selector, preview tương tác, giải thích học từ/flashcard, FAQ/footer; metadata/OG cơ bản. Không dùng testimonial hoặc số user giả. Paid checkout ghi chưa có.
- App: sidebar240px/mini68px, nhóm Discover/MyLearning/Personal/Account; collapse được lưu localStorage; active/title/focus; topbar pair/profile. Vue shell cũ đã dùng nhóm và cùng kích thước. Chưa hợp nhất hoàn toàn Blade/Vue shell.
- Reader: desktop nav thu gọn; Focus Mode ẩn nav và panel từ vựng, không lệ thuộc fullscreen API; giữ văn bản gốc, dịch bên dưới; các trạng thái load/failure/consent và chọn target. Đã sửa lớp che toolbar. Chapter list hiện vẫn dialog; chưa đạt panel trái/drawer thiết kế mới hoàn toàn.
- Mobile: Home/Library/Review/Progress/Profile, reader không bottom nav; Profile có MyStories/Import/Settings/Plans; toolbar ngang cuộn; card review4nút không chồng; tra từ dùng panel/bottom sheet hiện có. Cỡ chữ/theme giữ chức năng cũ; dark drawer không bị ép white.
- Design tokens navy/coral/blue/cream/white, buttons/cards/focus state, reduced motion. CSS inline cũ và lẫn ngôn ngữ giao diện còn debt; chưa có audit accessibility bằng công cụ/đọc màn hình đầy đủ.

Screenshots trong `qa-results/audit-*.png`: public desktop/mobile, dashboard, library320, reader320/focus, review completion/grades320, progress320/tablet, personal import. Những screenshot trước bản sửa cuối là bằng chứng giai đoạn, không thay kiểm tra render cuối.

### Routes và màn hình chính

|Route|Màn hình/operation|Bảo vệ|
|---|---|---|
|GET `/`, `/catalogue`, `/try/{story}`|Landing, browse, free preview|Preview chỉ published/free; catalogue backend filter.|
|POST `/try/{story}/translate`|Public preview translation|Source/target validation, throttle10/min.|
|GET/POST `/register`, GET/POST `/login`|Auth|Guest/register throttle; login rate limit, session/CSRF.|
|GET/POST `/onboarding`|Learning preferences|Auth; validated source/target/level/goal/timezone.|
|GET `/learn`, `/progress`, `/stories`|Dashboard, events activity, catalogue|Auth/owner scope; backend filters/pagination.|
|POST `/stories/{story}/start`, GET `/learn/books/{book}`|Enrollment/processing→reader|Published/source/access check, user lock/unique enrollment, owner poll.|
|GET `/chapters/read/{id}`|Vue interactive reader|Auth; chapter data APIs owner-scoped.|
|POST `/learning/translate-chapter`, `/learning/translate-word`|Reader/dictionary translation|Owner, pair validation, bounded input, private consent, throttle.|
|GET `/vocabulary/search`, GET `/review/{practice}/{book}/{chapter}`|Learning app|Auth; queue/data scoped user+source.|
|POST `/reviews`, `/reviews/submit`|Queue, grade persistence|State/pair/UUID checks, atomic history/goals; rate limit.|
|POST `/import`|Personal content|Auth, parser/input validation, private ownership and transaction.|
|GET `/editor/stories`, `/editor/stories/{story}/edit`; POST create/edit/publish/translations|Editorial CMS|Server editor/admin; expected source hash409; approval metadata.|
|POST `/backups/create`|Admin backup trigger|Admin+web CSRF+throttle; GET405. Real dump/restore not certified.|
|GET `/plans`|Free/Pro information|Auth; no real checkout/charge.|

Route cũ tự cộng số đọc từ review hoặc review achievement trả409; client review mới dùng submit duy nhất. Đây là thay đổi API có chủ đích để bảo vệ độ chính xác. Các API vocabulary legacy vẫn cần bổ sung pair/version guard cho mọi tab cũ ở giai đoạn tiếp.

## E. Multilingual Architecture

Bốn khái niệm:

1. Story Language = original `stories.language` / `chapters.language`.
2. Translation Language = `users.translation_language`, override bản dịch chapter chỉ áp dụng chapter display.
3. Learning Language = source preference `users.selected_language`, quyết định vocabulary/import/review; mặc định Spanish.
4. Interface Language = hiện vẫn UI Việt/Anh trộn, **chưa có locale preference/i18n hoàn chỉnh**. Không dùng translation target làm UI locale.

Mặc định tài khoản mới Spanish→English. Source được công bố hiện tại Spanish và English vì tokenizer tương ứng đã có; target config English/Spanish/Vietnamese/French/German/Italian/Portuguese. Target config thể hiện khả năng request provider, không đồng nghĩa mọi truyện có bản dịch đã duyệt. Live integration đã xác minh es→en; các target khác có tests pair isolation/mock, chưa chứng nhận chất lượng toàn bộ. Thêm source cần config và tokenizer/model được kiểm tra, không sửa logic mỗi page.

|Entity|Khóa/quan hệ|Trách nhiệm|
|---|---|---|
|users|Existing user ID; role/plan/source/target/level/goal/timezone|Preferences và permission; không cho public mass assign role.|
|stories|Unique slug, original language/body/glossary language|Catalogue chung. Hiện mỗi story một body, chưa normalized catalogue multi-chapter.|
|story_enrollments|FK user/story/book; unique(user,story)|Private learner snapshot bằng book/chapter; không tạo thêm copy vì target.|
|story_translations|FK story/reviewer; unique(story,source,target,source_hash)|Body dịch và available/processing/failed, unreviewed/approved, provider/reviewer/timestamps.|
|chapter_translations|FK chapter/reviewer; unique(chapter,source,target,source_hash)|Nhiều target cho một chapter; bản dịch không đổi original/progress.|
|translation_records|Unique(content_hash,source,target)|Shared provider cache/state/cooldown; không chứa source text.|
|saved_meanings|Unique(user,kind,item,target)|Nghĩa và SRS từng target, item source vẫn trong vocabulary/phrase legacy.|
|reading_completions|FK user; unique(user,chapter)|Snapshot word count/source; intentionally không cascade theo chapter deletion để giữ event.|
|flashcard_reviews|FK user; unique(user,request UUID); index(user,source,target,date)|Immutable grade/stage/due date history; item reference polymorphic, không FK cascade mất lịch sử khi xóa từ.|

Một original có thể nhiều targets. Hash+source làm bản dịch cũ không được dùng khi original sửa. Approved editorial được ưu tiên cho learner copy còn nguyên bản. Bản đã enrolled giữ nguyên khi catalogue sửa; chưa có version publication/sentence alignment hoặc tự merge edits vào learner copy. Chưa tạo `sentences/sentence_translations`; không báo sentence-aligned translation đã hoàn thành.

Provider hiện MyMemory: chunk450UTF-8bytes, max5000ký tự/request, timeout/connect timeout, validate quota/status/JSON,30day cache và DB persistent, failure retry cooldown, shared lock180s; endpoint throttles. Chưa có production billing/usage budgeting/provider selection; nội dung cá nhân cần consent gửi bên ngoài. Cache lock chỉ chia sẻ đa instance khi deploy dùng shared Redis tương ứng.

### Migration và rollback plan

Bảy migration2026_10_14 là bổ sung, đã áp dụng **chỉ database QA local**. Giữ migration cũ. Trước môi trường có dữ liệu thật: backup+restore drill, dry-run trên clone, kiểm tra FK/unique và số lượng trước/sau, dùng PHP/runtime tương thích, deploy code sau schema mở rộng. Không chạy RefreshDatabase trên database thật.

Rollback ưu tiên quay lại application version và giữ bảng mới; export bảng translations/reviews/saved_meanings/completions trước khi xem xét rollback schema. `down()` xóa dữ liệu mới: chỉ chạy khi có phê duyệt, backup và kế hoạch khôi phục. Không migrate rollback production trong phiên này. Old vocabulary schedules/meanings không bị batch reset; đổi target archive/restore. Old progress không được tái tạo giả từ lịch sử không có. Luồng xóa ngôn ngữ của user đã dọn cả archives/events mới; cache dịch liên quan bị evict và có thể tạo lại, không xóa dữ liệu học tài khoản khác. Không chạy thao tác xóa trên database QA browser hoặc production để chứng minh.

## F. Testing Results

Kết quả cuối được ghi trong `qa-results/final-tests.log`, `final-build.log`, `python-tests.log`, `npm-audit.json`, `php-syntax.log`, `pint.log` và `blade-syntax.log`.

|Kiểm tra|Trạng thái/thực tế|
|---|---|
|Laravel Feature/auth/integration suite|40 passed +1 test deprecated,194assertions; không có failed ở lần chạy cuối đã ghi. HTTP translation tests dùng mock khi kiểm tra branch/pair/quota.|
|Python path validator|1 test pass; kiểm tra allowed file, outside/missing/symlink/oversize. Python syntax compile pass.|
|Production frontend build|Pass. Vẫn Sass/dependency deprecations, không gọi warning-free.|
|PHP syntax / compiled Blade|245 file PHP source và49 compiled views:0syntax errors; xem log cuối. `view:cache` một mình không được coi runtime pass.|
|Pint|Format và check66 changed PHP files; xem log.|
|ESLint / TypeScript|Not configured trên frontend active; không báo pass.|
|npm audit|50 findings:17high,22moderate,11low,0critical sau Moment update. Chưa remediation toàn bộ.|
|Docker config|Parse config đã chạy; container build/runtime chưa chạy do daemon không hoạt động.|
|Live browser es→en|Read La panadería→machine translation→panadería/bakery và hambre/hunger saved→Good review→DB schedule/date persisted. Wordsread vẫn63 sau review.|
|Live personal import|Plaintext Spanish→private book9/chapter8→processed23words→reader→consent→English translation. Không public nội dung import.|
|Responsive|Public/dashboard/library/reader/review/progress ở320px, progress768×1024, desktop1280px; reader landscape740×360 kiểm tra. Đây là browser emulation, chưa physical iOS/Android matrix.|
|Admin/permission|Feature tests create/publish, learner deny, translation editorial deny, hash-conflict409. Chưa full CMS browser regression mọi thao tác.|
|Replay/ownership|Repeated finish, repeated review UUID, stale pair/card, private book import, unsafe URL, invalid language/type tests. Chưa multi-process stress test.|
|Performance|Build runtime được ghi; chưa Lighthouse/CWV/DB load profile trước–sau. Không claim cải thiện số liệu hiệu năng.|

E2E browser QA dùng CUA, không có Playwright suite độc lập/CI được bổ sung. Các luồng expired-session, provider failure/quota, no translation và consent có validation/tests; offline/network interception và every edge-case physical selection chưa bao phủ toàn bộ.

## G. Remaining Issues — thứ tự triển khai tiếp

1. **P1, nội dung/đa ngôn ngữ:** duyệt bản dịch Spanish→English của truyện xuất bản; MyMemory live có sai he/his cho Lucía/Ana. CMS approval đã có, chất lượng nội dung vẫn chưa đạt. Thêm publication revisions, source chapters và stable sentence IDs/alignment; chính sách chapter edit/regenerate tokenization; phục hồi processing.
2. **P1, dữ liệu legacy:** chuyển meanings/SRS working columns sang pair relation trực tiếp để giảm race với tab legacy; snapshot/review history mới đã tách, nhưng các số goals lịch sử và manual overrides không thể chứng nhận bằng events. Test concurrency đa process và cross-tab save khi onboarding đổi target.
3. **P1, bảo mật vận hành:** đánh giá tất cả upload/archive paths, EPUB zip bomb/memory/time caps, dictionary/model/admin endpoints; audit dependency exposure/remediation. Kiểm tra/rotate secret từng nằm trong Git nếu có; không chỉ bỏ `.env` khỏi tracking. Backup restore drill bắt buộc trước release.
4. **P2, reader:** chapter drawer/panel trái, closeable translation panel phải, sentence/phrase selection edgecases, scroll resume, audio chapter/sync nếu có nguồn audio; kiểm thử giọng Spanish trên thiết bị thật. Focus/font và core word-save đã chạy; không giả audio chapter.
5. **P2, library/CMS:** covers, favorites, translation filter, story detail/progress badges, chapter-count cho catalogue multi-chapter; edit language/level/topic UI, approval revoke và audit log. Search/CEFR/topic/access/pagination đã backend thật.
6. **P2, UX/i18n:** hợp nhất Vue/Blade shell, hoàn thiện Interface Language và translations UI, skeleton/toast/empty/error toàn app, screen-reader/contrast/dark-mode matrix; chapter mobile drawer và tránh mọi overlay stack.
7. **P2, import:** end-to-end TXT/EPUB/subtitles/YouTube/website với fixture thật và lỗi mạng/quyền/file xấu. UI đánh dấu Experimental; không công bố production support trước xác minh.
8. **P2, progress:** minutes/time heartbeat, reading position, learned-vocabulary semantics, chart và history drilldown/export; bảo toàn timezone lịch sử khi người dùng thay đổi.
9. **P3, release:** PHP8.2/8.3 baseline test thay host8.4 deprecations; dependency plan Vue/Vuetify/Mix; CI E2E, build containers, queue supervision/retries/monitoring, email deliverability, HTTPS/cookie headers, SEO per-story/CWV benchmark. Payment/provider/deploy cần lựa chọn và config của chủ sản phẩm.

Acceptance chưa đầy đủ: normalized multi-chapter/sentence model, every language pair integration, all import formats, complete CMS/audit, scroll/audio/readingminutes, full accessibility/security/performance testing. Không đổi phạm vi để đánh dấu xong.

## H. Production Readiness

|Module|Trạng thái|Điều kiện còn thiếu|
|---|---|---|
|Local install/build|Ready (local scope)|Supported PHP/container CI chưa xác minh.|
|Auth/onboarding/roles|Needs improvement|Mail delivery, verification policy, broader security tests.|
|Spanish→English core reading/save/review|Ready (verified local core flow)|Translation quality và device/edge-case matrix chưa ready release.|
|Multilingual storage/cache/approval|Needs improvement|Sentence alignment, normalized catalogue chapters, migrations on data clone, broader language/provider tests.|
|Vocabulary/flashcard|Needs improvement|Atomic new submit verified; pair relation migration và cross-tab/concurrency/load coverage còn thiếu.|
|Progress|Needs improvement|Events/timezone mới verified; history legacy, minutes/scroll chưa hoàn thiện.|
|Public/app/reader UI|Needs improvement|Shell/i18n/accessibility/complete reader panels.|
|Personal plain text import|Ready (local tested format)|Other formats/security resource limits còn thiếu.|
|EPUB/TXT/subtitles/YouTube/website import|Needs improvement|Không có full E2E certification từng format.|
|Editorial/admin|Needs improvement|Basic rights/edit/publish/approval verified; full CMS/audit chưa xong.|
|Backup/release infrastructure|Blocked|Restore drill, daemon/container runtime, shared cache/queue/monitoring/HTTPS/mail config.|
|Payment/deploy production|Blocked|Chưa chọn/config provider; không tích hợp hoặc deploy theo yêu cầu.|

Không có bằng chứng mất dữ liệu cũ trong QA; dữ liệu lịch sử được giữ, migrations additive. Không suy rộng thành bảo đảm production chưa truy cập. Release chỉ nên thực hiện sau khi đóng các blocker ở trên.

## AI translation follow-up — 2026-10-14

Local Qwen3.5:4b through Ollama is now enabled. See `docs/translation/AI_TRANSLATION.md` for configuration, cache/prompt revision, privacy behavior, measured quality and cold-start limits. Live La panadería es→en generated in21.39s, cached lookup0.007s. New tests:46pass+1deprecated,217assertions; build pass. Updated provider selection does not modify learner meanings/schedules or approved translations. Browser visual QA for this follow-up was blocked by the tool URL policy and is not marked pass. No production rollout or cloud paid integration occurred.

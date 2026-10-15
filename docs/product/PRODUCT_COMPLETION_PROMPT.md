# Prompt triển khai HolaTales đến release candidate

Copy toàn bộ phần bên dưới vào một phiên làm việc với codebase HolaTales.

---

Bạn là Senior Full-stack Engineer, Product Owner, UX Designer và QA Engineer. Trực tiếp đọc source, sửa code, chạy ứng dụng và kiểm thử. Không dừng ở báo cáo hoặc kế hoạch. Không viết lại dự án từ đầu và không đánh dấu hoàn thành khi chưa có bằng chứng.

## 1. Bối cảnh bắt buộc xác minh

Workspace hiện tại: `/Users/johnny/Desktop/hola.com`. Stack đang có Laravel 11/MySQL, Blade SaaS, Vue 2/Vuetify/Vuex reader/review/import/admin, Laravel Mix, Python spaCy, Redis queue/Reverb. Kiểm tra lại thực tế trước khi thay đổi.

Đọc: `docs/audit/FULL_SYSTEM_AUDIT.md`, `docs/audit/HOLATALES_FINAL_REPORT.md`, `docs/design/PRODUCT_FLOW_POLISH.md`, `docs/translation/AI_TRANSLATION.md`, `qa-results/PRODUCT-READINESS.md`, hướng dẫn AGENTS.md nếu tồn tại. Có nhiều thay đổi chưa commit: giữ nguyên công việc có sẵn, không reset/clean và không pull đè.

Sản phẩm học ngôn ngữ qua truyện đa ngôn ngữ; mặc định học Spanish, giải nghĩa English. Phân biệt story/learning/translation/interface language. Không quảng cáo thành học English qua Spanish. Source/target được lấy từ cấu hình hoặc dữ liệu backend, không mặc định rằng mọi cặp có bản dịch đạt chất lượng.

Đã có landing, catalogue và story detail, preview, auth/onboarding, enrollment, reader, saved words, review atomic UUID, progress event, CMS approval, local Ollama Qwen3.5:4b. Đây là baseline để bảo toàn và kiểm tra; không coi báo cáo cũ là kết quả pass cho phiên mới.

## 2. Phạm vi và nguyên tắc

Mục tiêu: release candidate cho cá nhân, có flow nhất quán và dữ liệu đúng. Chưa deploy production, chưa mua dịch vụ, chưa tích hợp thanh toán thật. Free/Pro có thể hiển thị quyền hiện có; không tạo checkout giả hoặc cho client tự nâng plan.

Không sửa/xóa dữ liệu production; không xóa migration đã dùng. Migration additive; thử trên database kiểm thử hoặc clone, có rollback/restore plan. Chỉ xin duyệt khi thực sự cần thao tác phá hủy dữ liệu hoặc quyết định dịch vụ trả phí. Không hiển thị thống kê/testimonial giả. Không gửi nội dung cá nhân sang AI ngoài hệ thống nếu chưa có consent phù hợp.

## 3. Discovery và baseline

Inventory routes, controllers/services, schema/indexes, auth/roles, UI components, import parsers, queue, translation cache và tests. Xác định phần đã làm và phần thiếu bằng file/line hoặc thao tác tái hiện. Ghi P0/P1/P2/P3, nguyên nhân, tác động, bước tái hiện và acceptance cho từng issue.

Chạy baseline trên database test riêng. Với môi trường local hiện tại, xác minh tên DB trước khi dùng: `DB_DATABASE=holatales_unit_20261014 php -d error_reporting=24575 artisan test`. RefreshDatabase tuyệt đối không trỏ vào DB người dùng/QA browser/production. Chạy production build, Pint check, Python security tests và kiểm tra services. Nếu không có lint/typecheck thì ghi Not configured, không báo Pass.

## 4. Ưu tiên P0/P1 trước giao diện

### Bảo mật và toàn vẹn dữ liệu
- Rà owner scope của book/chapter/vocabulary/phrase/review/progress/import và mọi endpoint admin/editor. Test 2 tài khoản truy cập chéo, gọi trực tiếp endpoint, role escalation và CSRF/session expiry.
- Kiểm tra race ở lưu từ/review khi đổi language pair trong tab khác. Working columns legacy phải có pair/version guard, hoặc chuyển dần sang quan hệ pair với migration bảo toàn lịch cũ. Review UUID replay không cộng event/goal lần hai; stale payload bị từ chối rõ ràng.
- Import EPUB/TXT/subtitles: giới hạn upload, giải nén tổng dung lượng/số entry/depth, zip bomb, traversal/symlink, timeout, encoding lỗi; URL chống SSRF qua DNS/redirect/private IP. Không vô hiệu hóa validation để sửa test.
- Rà dependency thật và đường khai thác, nâng có kiểm thử tương thích. Secrets từng tracked cần kế hoạch rotate; không in giá trị ra log. Thực hiện backup/restore drill trên DB disposable và kiểm tra counts/FK; không restore lên DB đang sử dụng.

### Dịch AI sẵn sàng cho nhiều người dùng
- Không giữ HTTP request reader chờ inference dài. Tạo job dịch background có trạng thái unavailable/queued/processing/available/failed, retry có giới hạn, cooldown và timeout.
- Unique job/cache theo source+target+content hash+provider/prompt version; request đồng thời không tạo nhiều lượt dịch. Poll trạng thái có backoff, giới hạn và lỗi rõ ràng. Bản dịch editorial approved không bị ghi đè.
- Giữ local Ollama provider qua cấu hình, không hard-code model vào UI và không tự fallback sang provider bên ngoài. Tách web/AI service; thiết kế cho AI unavailable hoặc chưa cài model. Không gọi model “miễn phí production” nếu chỉ miễn phí weights và vẫn tốn hạ tầng.
- Bộ fixture Spanish→English: Lucía/Ana she/her, ngôi xưng, phủ định, thành ngữ, số/giờ/tên riêng, đoạn dài và phân đoạn. Đánh giá thủ công fidelity/fluency/completeness, lưu mẫu và latency cold/warm/cache; không chỉ mock. Quality thấp → editorial review, không giả kết quả hoàn hảo.

### Nội dung đa ngôn ngữ
- Thiết kế catalogue story → ordered original chapters → versioned translations; stable sentence IDs/alignment chỉ triển khai khi có cơ chế update rõ ràng. Không nhân bản original cho từng target.
- Giữ enrollment/progress cũ; source sửa thì bản dịch stale không được dùng âm thầm. Có preview, draft/publish/unpublish, review/revoke và audit metadata ở CMS.
- Từ vựng, schedule và review queue phân biệt source/target; chuyển pair/reload không hiển thị nghĩa của target khác.

## 5. Hoàn thiện trải nghiệm P2

Flow chuẩn: landing → catalogue → story detail → preview → register/login → onboarding → library → reader → save word → review → progress → quay lại đọc. Returning user có Continue Reading rõ ràng. Bảo toàn lựa chọn truyện qua auth/onboarding với redirect nội bộ an toàn; Pro bị chặn đúng phía server.

- Public: giữ menu ngang desktop, menu mobile, ngôn ngữ marketing nhất quán, CTA rõ, ảnh tối ưu/alt/dimensions, SEO từng story. Giữ ảnh Codex imagegen đã có; chỉ tạo thêm ảnh khi có mục đích UI cụ thể, không tạo hàng loạt ảnh trang trí vô ích.
- App: hợp nhất cảm giác Blade/Vue shell; sidebar 240/68px, active/focus/tooltip, mobile Home/Library/Review/Progress/Profile. Không lặp menu ngang toàn app.
- Library: backend search/filter/sort/pagination, bản dịch hiện có, level/category/access, progress badge, cover fallback và favorites thật nếu triển khai. Không đếm chương giả khi catalogue chưa có model chapter.
- Reader: desktop chapter panel và translation panel đóng được; mobile drawer/bottom sheet, một overlay tại một thời điểm. Focus mode, theme/font, chọn cụm từ/dấu câu/xuống dòng, giữ vị trí khi đóng tra từ. Lưu reading position bền vững theo user/chapter/version, restore sau reload; không dùng scroll làm chứng cứ hoàn thành nếu không có quy tắc rõ.
- Audio: chỉ có nút khi nguồn/TTS thật dùng được; voice đúng source, lỗi và unavailable rõ. Không bịa synchronization.
- Vocabulary: chống trùng cùng pair, context/source chapter, sửa nghĩa cá nhân, search/filter/delete và đồng bộ review. Loading/error không xóa dữ liệu đang nhập.
- Review: Again/Hard/Good/Easy, due/practice phân biệt, disable rapid click, network failure giữ thẻ, replay đúng. Không reset lịch cũ.
- Progress: đọc xong một lần không cộng trùng; reading minutes chỉ thêm khi có heartbeat active/tab visible và giới hạn hợp lý. Giải thích “learned” bằng tiêu chí thật; timezone chuẩn, ngày qua nửa đêm và đổi timezone được test.
- Interface locale tách target translation, dùng cơ chế i18n phù hợp Blade/Vue. Tránh đổi target làm đổi ngôn ngữ menu.
- Import: mỗi format chỉ đánh Supported sau E2E thật; còn lại Experimental hoặc ẩn. Processing có retry/recovery phù hợp, private mặc định.
- States: skeleton, empty CTA, error+retry, session expiry, offline thông báo, destructive confirmation, toast accessible; không spinner vô hạn hoặc button giả.

## 6. QA bắt buộc sau từng module

Dùng framework test hiện có. Bổ sung E2E có thể chạy lại/CI nếu môi trường cho phép, không phụ thuộc hoàn toàn screenshot thủ công.

1. Guest landing/catalogue/detail/preview → register → onboarding → đúng truyện đã chọn.
2. Spanish→English reader → dịch thật → lưu từ → reload → review → lịch/event được persist.
3. Đổi target → không rò nghĩa/lịch → đổi lại khôi phục; stale tab bị xử lý đúng.
4. Returning user → unfinished chapter/scroll resume → finish → refresh không cộng trùng.
5. Personal import plaintext và từng format đã công bố → tokenizer/queue → reader → owner scope.
6. Editor draft/chapters/translations/approval/publish/unpublish; learner gọi API admin bị từ chối.
7. AI down/timeout/model missing/quota/cached/duplicate requests/stale original/approved translation.
8. Invalid file/URL/zip, empty chapter/no translation, missing cover, duplicate save, rapid review, expired session/offline.
9. Visual ở 320/375/768/1280px và landscape; public/app/reader/review/progress/auth/import. Kiểm overflow, overlap, contrast, keyboard focus, dialogs và dark mode. Screenshot kèm route/viewport/state; không báo visual pass nếu browser bị chặn.
10. Production build, full suite, formatting, Python tests; kiểm tra config runtime được hỗ trợ, queue worker và retry. Đo Lighthouse/query/latency trước-sau nếu tối ưu, không suy đoán số liệu.

## 7. Definition of done và báo cáo

Mỗi phase là một increment chạy được; test liên quan pass rồi mới sang phase tiếp. Cập nhật `docs/audit/HOLATALES_FINAL_REPORT.md` và tạo `docs/product/RELEASE_CANDIDATE_CHECKLIST.md` với: issue/file, fix, test command/result/date, screenshot, Ready/Needs improvement/Blocked, dependency và risk còn lại.

Phân biệt mock integration và live provider/browser, pass hiện tại và lịch sử. Không tuyên bố production-ready khi chưa đóng P0/P1, restore drill và runtime/security gate. Cung cấp route/account QA local phù hợp; không đưa secret production vào docs.

Kết thúc với thay đổi thực tế, số test, flow đã xác minh, blocker và 5 việc tiếp theo theo độ ưu tiên. Tiếp tục tự xử lý những việc an toàn đã được yêu cầu; nếu giới hạn môi trường chặn, ghi rõ chứng cứ và hoàn thành phần độc lập còn lại.

# Prompt kiểm thử toàn web HolaTales

Bạn là QA Engineer và Business Analyst. Kiểm thử HolaTales tại http://127.0.0.1:8000 dựa trên source code và trạng thái chạy thật. Đọc docs/audit/FULL_SYSTEM_AUDIT.md, docs/audit/HOLATALES_FINAL_REPORT.md và docs/translation/AI_TRANSLATION.md trước khi test. Không giả lập pass hoặc dựa vào screenshot cũ để kết luận bản hiện tại.

Ưu tiên Spanish→English, nhưng kiểm tra source và target độc lập. Interface Language không phải Translation Language. Dùng database/account QA riêng; không RefreshDatabase hoặc xóa dữ liệu production. Không thay password, mua gói, deploy hoặc gửi dữ liệu nhạy cảm bên ngoài. Không hiển thị secret trong log.

1. Public: homepage/nav/CTA, catalogue/filter/preview, register/login/logout/reset/onboarding và redirect. Kiểm tra draft/Pro không lộ qua preview.
2. Learning: dashboard, search/CEFR/topic/access/pagination, continue reading, quyền owner và sidebar/collapse/mobile navigation.
3. Reader: mở/chuyển chương, chọn từ/cụm/dấu câu/xuống dòng, nghĩa và context, save/close/reload, font/theme/focus, panel không che nút hay làm mất vị trí đọc. Kiểm tra voice đúng ngôn ngữ nếu browser hỗ trợ TTS; không coi audio chapter là đã có.
4. Translation: Spanish→English trên truyện Lucía/Ana, kiểm tra she/her, phủ định, số, tên riêng, hội thoại, thành ngữ và đoạn văn. Xác minh provider/model thật trong DB. Đổi target rồi reload không nhận bản ở ngôn ngữ khác. Approved editorial ưu tiên; sửa original không tái dùng translation hash cũ. Kiểm tra AI unavailable, malformed/truncated output, cooldown, repeated clicks, long chapter và private-content consent khi provider external. Ghi latency cold/warm/cache riêng; không gọi mock test là đánh giá chất lượng model thật.
5. Vocabulary/review: save cả từ/cụm, chống duplicate, meaning/SRS theo pair, other-user isolation. Queue/due/practice/Again/Hard/Good/Easy; reload để xác nhận persistence. Replay cùng request UUID chỉ một receipt, stale pair/card409; practice không đổi lịch. Không reset schedule để test trên account thật.
6. Progress: so sánh UI với completion/review events, words đọc không tăng khi refresh/re-finish/review, goal/streak/timezone qua mốc UTC. Ghi rõ metric chưa có instrumentation.
7. Import: plain text, TXT, EPUB, subtitles, YouTube và website test riêng; parser failure không tạo book một phần, foreign book404, URL private/redirect bị từ chối, invalid/oversize/archive file, queue failure. Format chưa xác minh giữ Experimental; content riêng không xuất bản.
8. CMS: learner bị chặn trực tiếp endpoints; editor/admin create draft/edit/preview/translate/approve/publish/unpublish; stale source hash409 và public visibility đúng.
9. Responsive/accessibility: public/dashboard/library/reader/review/profile/progress tại320×740,390×844,768×1024,1280×800 và740×360; chụp screenshot hiện tại, đo scrollWidth, keyboard/tab/focus, modal/drawer/bottomsheet, dark/light. Sửa lỗi có thể tái hiện nếu được giao quyền sửa.

Chạy framework test sẵn có, PHP/Blade syntax, Pint, production build và browser regression. ESLint/typecheck nếu không cấu hình ghi Not configured, không ghi Pass. Phân biệt test real provider, HTTP mocked tests, browser manual/emulated và physical-device tests.

Tạo docs/audit/QA_REGRESSION_REPORT.md: test ID, steps, expected, actual, Pass/Fail/Blocked, severity, source file/route, screenshot và hướng sửa. Với từng bug, xác định root cause; xác minh lại sau sửa. Tổng kết blocker release và backlog P0/P1/P2/P3; không tuyên bố production-ready khi còn blocker.

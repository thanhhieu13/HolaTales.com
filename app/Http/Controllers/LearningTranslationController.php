<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\EncounteredWord;
use App\Models\Story;
use App\Services\CatalogueService;
use App\Services\ContentTranslationService;
use App\Services\LearningTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearningTranslationController extends Controller
{
    public function sample(Request $r, Story $story, ContentTranslationService $translator)
    {
        $d = $r->validate(['target' => 'sometimes|in:'.implode(',', array_keys(config('learning.targets')))]);
        $target = $d['target'] ?? $r->user()?->translation_language ?? config('learning.default_target');
        $story = app(CatalogueService::class)->publicStory($story);
        abort_unless($story->published_at && ! $story->is_premium, 404);
        try {
            return response()->json($translator->request($story, $target));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Không dịch được lúc này. Hãy thử lại sau.'], 503);
        }
    }

    public function chapter(Request $r, ContentTranslationService $translator)
    {
        $d = $r->validate(['chapterId' => 'required|integer', 'allow_external' => 'sometimes|accepted', 'target' => 'sometimes|in:'.implode(',', array_keys(config('learning.targets')))]);
        $chapter = Chapter::where('user_id', $r->user()->id)->findOrFail($d['chapterId']);
        abort_unless(array_key_exists($chapter->language, config('learning.sources')) && $chapter->type === 'text', 422, 'Ngôn ngữ truyện chưa được hỗ trợ hoặc đây không phải chương văn bản.');
        abort_if(mb_strlen($chapter->raw_text) > 5000, 422, 'Truyện dài quá 5.000 ký tự; cần chia đoạn trước khi dịch.');
        $curated = DB::table('story_enrollments')->where('user_id', $r->user()->id)->where('book_id', $chapter->book_id)->exists();
        if (! $curated && app(LearningTranslationService::class)->isExternal() && ! $r->boolean('allow_external')) {
            return response()->json(['message' => 'Để dịch nội dung riêng, hãy đồng ý gửi văn bản sang MyMemory.', 'requiresConsent' => true], 409);
        }
        try {
            return response()->json($translator->request($chapter, $d['target'] ?? $r->user()->translation_language) + ['curated' => $curated]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Không dịch được lúc này. Dịch vụ có thể hết hạn mức hoặc mất kết nối; hãy thử lại sau.'], 503);
        }
    }

    public function word(Request $r, LearningTranslationService $translator)
    {
        $d = $r->validate(['wordId' => 'required|integer']);
        $word = EncounteredWord::where('user_id', $r->user()->id)->findOrFail($d['wordId']);
        abort_unless(array_key_exists($word->language, config('learning.sources')), 422);
        abort_unless($r->header('X-Learning-Source') === $word->language && $r->header('X-Learning-Target') === $r->user()->translation_language, 409, 'Language pair changed. Reload before requesting a meaning.');
        if ($word->translation) {
            return response()->json(['status' => 'available', 'translation' => $word->translation, 'source' => 'saved']);
        }
        $stories = Story::join('story_enrollments', 'stories.id', '=', 'story_enrollments.story_id')->where('story_enrollments.user_id', $r->user()->id)->where('stories.language', $word->language)->select('stories.*')->get();
        $matches = $stories->filter(fn ($s) => preg_match('/\b'.preg_quote($word->word, '/').'\b/iu', $s->body));
        if ($matches->isEmpty() && $translator->isExternal()) {
            return response()->json(['translation' => null, 'message' => 'Từ thuộc nội dung riêng; hãy nhập nghĩa hoặc bật từ điển dịch phù hợp.']);
        }
        foreach ($matches as $story) {
            if ($story->glossary_language === $r->user()->translation_language && ($meaning = $story->glossary[mb_strtolower($word->word)] ?? null)) {
                return response()->json(['status' => 'available', 'translation' => $meaning, 'source' => 'glossary']);
            }
        }
        try {
            return response()->json($translator->request($word->word, config('learning.sources.'.$word->language.'.code'), $r->user()->translation_language));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Tự dịch từ chưa khả dụng. Bạn có thể nhập nghĩa và thử lại sau.'], 503);
        }
    }
}

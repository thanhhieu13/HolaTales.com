<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\EncounteredWord;
use App\Models\Goal;
use App\Models\GoalAchievement;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\User;
use App\Services\CatalogueService;
use App\Services\ChapterService;
use App\Services\GoalService;
use App\Services\TranslationPreferenceService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LearningController extends Controller
{
    public function landing()
    {
        return view('saas.landing', ['story' => Story::whereNotNull('published_at')->where('is_premium', false)->orderByRaw('language = ? DESC', ['spanish'])->first()]);
    }

    public function sample(Story $story)
    {
        abort_unless($story->published_at && ! $story->is_premium, 404);

        $story = app(CatalogueService::class)->publicStory($story);

        return view('saas.sample', compact('story'));
    }

    public function catalogue(Request $r)
    {
        $d = $r->validate(['language' => 'sometimes|in:'.implode(',', array_keys(config('learning.sources'))), 'target' => 'sometimes|in:'.implode(',', array_keys(config('learning.targets')))]);

        return view('saas.catalogue', ['stories' => Story::whereNotNull('published_at')->where('language', $d['language'] ?? 'spanish')->get(), 'target' => $d['target'] ?? 'en']);
    }

    public function registerForm(Request $request)
    {
        $data = $request->validate(['story' => 'sometimes|integer', 'target' => 'sometimes|in:'.implode(',', array_keys(config('learning.targets')))]);
        if (isset($data['story'])) {
            $story = Story::whereNotNull('published_at')->findOrFail($data['story']);
            $request->session()->put('pending_story', $story->id);
            $request->session()->put('pending_story_target', $data['target'] ?? config('learning.default_target'));
            $request->session()->put('url.intended', '/catalogue/'.$story->id);
        }

        return view('saas.register');
    }

    public function register(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $data = $r->validate(['name' => 'required|string|max:64', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|confirmed|min:8|max:72']);
        $user = DB::transaction(fn () => (new UserService)->createUser($data['name'], strtolower($data['email']), $data['password'], false, true));
        if (config('mail.default') !== 'log') {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
                session()->flash('status', 'Tài khoản đã tạo; email xác minh chưa gửi được. Vui lòng thử lại sau.');
            }
        }
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/onboarding');
    }

    public function onboarding()
    {
        $onboardingStory = Story::whereNotNull('published_at')->find(session('pending_story'));

        return view('saas.onboarding', compact('onboardingStory'));
    }

    public function saveOnboarding(Request $r)
    {
        // Story and translation languages are independent preferences.
        $d = $r->validate(['language' => 'required|in:'.implode(',', array_keys(config('learning.sources'))), 'level' => 'required|in:A1,A2,B1', 'translation_language' => 'required|in:'.implode(',', array_keys(config('learning.targets'))), 'daily_word_goal' => 'required|integer|in:100,200,500', 'learning_timezone' => 'sometimes|required|timezone']);
        $u = $r->user();
        DB::transaction(function () use ($u, $d) {
            (new TranslationPreferenceService)->change($u, $d['translation_language']);
            $u->selected_language = $d['language'];
            $u->learning_level = $d['level'];
            $u->translation_language = $d['translation_language'];
            $u->daily_word_goal = $d['daily_word_goal'];
            $u->learning_timezone = $d['learning_timezone'] ?? $u->learning_timezone ?? 'UTC';
            $u->onboarded_at = now();
            $u->save();
            (new GoalService)->createGoalsForLanguage($u->id, $d['language']);
            Goal::where('user_id', $u->id)->where('language', $d['language'])->where('type', 'read_words')->update(['quantity' => $d['daily_word_goal']]);
        });

        $pendingStory = Story::whereNotNull('published_at')->find($r->session()->pull('pending_story'));
        $r->session()->forget(['pending_story_target', 'url.intended']);
        if ($pendingStory) {
            return redirect('/catalogue/'.$pendingStory->id.'?target='.$u->translation_language);
        }

        return redirect('/learn')->with('status', 'Bạn đã sẵn sàng. Chọn một truyện và bắt đầu đọc!');
    }

    public function dashboard(Request $r)
    {
        if (! $r->user()->onboarded_at) {
            return redirect('/onboarding');
        }
        $u = $r->user();
        $learningToday = now($u->learning_timezone)->startOfDay();
        $chapters = Chapter::where('user_id', $u->id)->where('language', $u->selected_language);
        $continue = (clone $chapters)->where('read_count', 0)->latest('updated_at')->first();
        $words = EncounteredWord::where('user_id', $u->id)->where('language', $u->selected_language);
        $snapshots = DB::table('reading_completions')->where('user_id', $u->id)->where('language', $u->selected_language)->get();
        $legacyRead = (clone $chapters)->where('read_count', '>', 0)->whereNotIn('id', $snapshots->pluck('chapter_id'))->get()->sum(fn ($c) => $c->word_count);
        $stats = ['read' => $snapshots->sum('word_count') + $legacyRead,
            'completed' => (clone $chapters)->where('read_count', '>', 0)->count(), 'saved' => (clone $words)->where('stage', '<', 0)->count(),
            'due' => (clone $words)->where('stage', '<', 0)->where(fn ($q) => $q->whereDate('next_review', '<=', $learningToday->toDateString())->orWhere('relearning', true))->count()];
        $readingGoal = Goal::where('user_id', $u->id)->where('language', $u->selected_language)->where('type', 'read_words')->first();
        $stats['today'] = GoalAchievement::where('user_id', $u->id)->where('language', $u->selected_language)->where('goal_id', $readingGoal?->id)->whereDate('day', $learningToday->toDateString())->sum('achieved_quantity');
        $days = GoalAchievement::where('user_id', $u->id)->where('language', $u->selected_language)->where('achieved_quantity', '>', 0)->orderByDesc('day')->pluck('day')->unique()->map(fn ($d) => substr($d, 0, 10));
        $streak = 0;
        $day = $learningToday->copy();
        if (! $days->contains($day->toDateString())) {
            $day->subDay();
        }
        while ($days->contains($day->toDateString())) {
            $streak++;
            $day->subDay();
        }
        $stats['streak'] = $streak;
        $stats['active_minutes'] = intdiv((int) DB::table('reading_activity_days')->where('user_id', $u->id)->where('language', $u->selected_language)->sum('seconds'), 60);
        $recommended = Story::whereNotNull('published_at')->where('language', $u->selected_language)->orderByRaw('level = ? DESC', [$u->learning_level])->get();

        $activity = [];
        if ($r->is('progress')) {
            for ($i = 13; $i >= 0; $i--) {
                $activity[$learningToday->copy()->subDays($i)->toDateString()] = ['words' => 0, 'reviews' => 0];
            }
            foreach ($snapshots as $event) {
                $day = Carbon::parse($event->created_at, config('app.timezone'))->setTimezone($u->learning_timezone)->toDateString();
                if (isset($activity[$day])) {
                    $activity[$day]['words'] += $event->word_count;
                }
            }
            $cutoff = $learningToday->copy()->subDays(13)->setTimezone(config('app.timezone'));
            $reviews = DB::table('flashcard_reviews')->where('user_id', $u->id)->where('source', $u->selected_language)->where('target', $u->translation_language)->where('created_at', '>=', $cutoff)->get();
            foreach ($reviews as $event) {
                $day = Carbon::parse($event->created_at, config('app.timezone'))->setTimezone($u->learning_timezone)->toDateString();
                if (isset($activity[$day])) {
                    $activity[$day]['reviews']++;
                }
            }
        }

        return view('saas.dashboard', compact('stats', 'continue', 'recommended', 'activity') + ['translations' => $this->availableTranslations($recommended)]);
    }

    public function library(Request $r)
    {
        if (! $r->user()->onboarded_at) {
            return redirect('/onboarding');
        }
        $filters = $r->validate(['level' => 'nullable|in:A1,A2,B1', 'q' => 'nullable|string|max:100', 'topic' => 'nullable|string|max:64', 'access' => 'nullable|in:free,pro']);
        $stories = Story::whereNotNull('published_at')->where('language', $r->user()->selected_language)
            ->when($filters['level'] ?? null, fn ($q, $v) => $q->where('level', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$v.'%')->orWhere('topic', 'like', '%'.$v.'%')))
            ->when($filters['topic'] ?? null, fn ($q, $v) => $q->where('topic', $v))
            ->when($filters['access'] ?? null, fn ($q, $v) => $q->where('is_premium', $v === 'pro'))
            ->orderBy('title')->paginate(12)->withQueryString();

        return view('saas.library', ['stories' => $stories, 'translations' => $this->availableTranslations($stories->getCollection()), 'topics' => Story::whereNotNull('published_at')->where('language', $r->user()->selected_language)->distinct()->orderBy('topic')->pluck('topic')]);
    }

    public function start(Request $r, Story $story)
    {
        $u = $r->user();
        if (! $u->onboarded_at) {
            return redirect('/onboarding');
        }
        $story = app(CatalogueService::class)->publicStory($story);
        abort_unless($story->published_at && $story->language === $u->selected_language, 404);
        if ($story->is_premium && $u->plan !== 'pro') {
            return redirect('/plans')->with('status', 'Truyện này thuộc thư viện Pro.');
        }
        $bookId = DB::transaction(function () use ($u, $story) {
            // Serialize starts per learner, preventing duplicate enrolments on double click.
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('story_enrollments')->where('user_id', $u->id)->where('story_id', $story->id)->first();
            if ($existing) {
                return $existing->book_id;
            }
            $book = Book::create(['user_id' => $u->id, 'name' => $story->title, 'language' => $story->language, 'cover_image' => null]);
            // Tokenization stays in the existing background queue and reader.
            if ($story->relationLoaded('published_chapters')) {
                foreach ($story->getRelation('published_chapters') as $original) {
                    $before = Chapter::where('book_id', $book->id)->max('id') ?? 0;
                    (new ChapterService)->createChapter($u->id, $u->uuid, $book->id, $original->title, $original->body);
                    Chapter::where('book_id', $book->id)->where('id', '>', $before)->update(['story_chapter_revision_id' => $original->id]);
                }
            } else {
                (new ChapterService)->createChapter($u->id, $u->uuid, $book->id, $story->title, $story->body);
            }
            DB::table('story_enrollments')->insert(['user_id' => $u->id, 'story_id' => $story->id, 'book_id' => $book->id, 'story_publication_id' => $story->current_publication_id, 'created_at' => now(), 'updated_at' => now()]);

            return $book->id;
        });

        return redirect('/learn/books/'.$bookId);
    }

    public function book(Request $r, Book $book)
    {
        abort_unless($book->user_id === $r->user()->id, 404);
        $chapter = Chapter::where('book_id', $book->id)->where('user_id', $r->user()->id)->orderByRaw('read_count = 0 DESC')->orderBy('id')->firstOrFail();
        if ($chapter->processing_status === 'processed') {
            return redirect('/chapters/read/'.$chapter->id);
        }

        return view('saas.processing', compact('chapter', 'book'));
    }

    public function plans()
    {
        return view('saas.plans');
    }

    public function editor(Request $r)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);

        return view('saas.editor', ['stories' => Story::latest()->get()]);
    }

    public function saveStory(Request $r)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
        $d = $r->validate(['title' => 'required|string|max:128', 'slug' => 'required|alpha_dash|max:128|unique:stories', 'level' => 'required|in:A1,A2,B1',
            'language' => 'required|in:'.implode(',', array_keys(config('learning.sources'))), 'topic' => 'required|string|max:64', 'summary' => 'required|string|max:500', 'body' => 'required|string|max:15000', 'is_premium' => 'sometimes|boolean']);
        DB::transaction(function () use ($d) {
            $story = Story::create($d + ['glossary_language' => 'en', 'glossary' => [], 'published_at' => null]);
            app(CatalogueService::class)->ensureOriginal($story);
        });

        return redirect('/editor/stories')->with('status', 'Đã lưu bản nháp.');
    }

    public function publish(Request $r, Story $story)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
        $data = $r->validate(['action' => 'sometimes|in:publish,unpublish']);
        $action = $data['action'] ?? ($story->published_at ? 'unpublish' : 'publish');
        if ($action === 'publish') {
            app(CatalogueService::class)->publish($story, $r->user()->id);
        } else {
            DB::transaction(function () use ($story, $r) {
                $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
                $story->published_at = null;
                $story->save();
                app(CatalogueService::class)->audit($story, $r->user()->id, 'unpublished');
            });
        }

        return back()->with('status', 'Đã cập nhật trạng thái xuất bản.');
    }

    public function editStory(Request $r, Story $story)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
        $translations = DB::table('story_translations')->where('story_id', $story->id)->where('source', config('learning.sources.'.$story->language.'.code'))->where('source_hash', hash('sha256', $story->body))->get();

        return view('saas.story-editor', compact('story', 'translations') + ['originalChapters' => StoryChapter::where('story_id', $story->id)->orderBy('position')->get(), 'originalTranslations' => DB::table('story_chapter_translations')->whereIn('story_chapter_id', StoryChapter::where('story_id', $story->id)->pluck('id'))->get()->groupBy('story_chapter_id'), 'publications' => DB::table('story_publications')->where('story_id', $story->id)->orderByDesc('version')->get()]);
    }

    public function updateStory(Request $r, Story $story)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
        $data = $r->validate(['title' => 'required|string|max:128', 'summary' => 'required|string|max:500', 'body' => 'required|string|max:15000', 'expected_hash' => 'required|string|size:64']);
        DB::transaction(function () use ($story, $data, $r) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(hash('sha256', $story->body), $data['expected_hash']), 409, 'Nội dung đã thay đổi. Tải lại trước khi chỉnh sửa.');
            $story->update(array_diff_key($data, ['expected_hash' => true]));
            $service = app(CatalogueService::class);
            $service->ensureOriginal($story);
            $original = StoryChapter::where('story_id', $story->id)->orderBy('position')->firstOrFail();
            if ($original->body !== $story->body) {
                $original->body = $story->body;
                $original->revision++;
                $original->save();
                $service->recordRevision($original, $r->user()->id);
            }
            $service->audit($story, $r->user()->id, 'draft_updated');
        });

        return back()->with('status', 'Đã lưu. Bản dịch của nội dung cũ được giữ trong lịch sử và không dùng cho bản mới. Sách người học đã bắt đầu được giữ nguyên.');
    }

    public function saveStoryTranslation(Request $r, Story $story)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
        $data = $r->validate(['target' => 'required|in:'.implode(',', array_keys(config('learning.targets'))), 'body' => 'required|string|max:30000', 'expected_hash' => 'required|string|size:64']);
        DB::transaction(function () use ($r, $story, $data) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(hash('sha256', $story->body), $data['expected_hash']), 409, 'Bản gốc đã thay đổi. Tải lại trước khi duyệt bản dịch.');
            DB::table('story_translations')->updateOrInsert(['story_id' => $story->id, 'source' => config('learning.sources.'.$story->language.'.code'), 'target' => $data['target'], 'source_hash' => $data['expected_hash']], ['body' => $data['body'], 'status' => 'available', 'review_status' => 'approved', 'provider' => 'Editorial', 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('content_audit_events')->insert(['story_id' => $story->id, 'actor_id' => $r->user()->id, 'action' => 'translation_approved', 'target' => $data['target'], 'source_hash' => $data['expected_hash'], 'created_at' => now()]);
        });

        return back()->with('status', 'Đã lưu bản dịch được biên tập và duyệt.');
    }

    public function revokeStoryTranslation(Request $r, Story $story)
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
        $data = $r->validate(['target' => 'required|in:'.implode(',', array_keys(config('learning.targets'))), 'expected_hash' => 'required|string|size:64']);
        DB::transaction(function () use ($r, $story, $data) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(hash('sha256', $story->body), $data['expected_hash']), 409, 'Source changed. Reload before revoking approval.');
            $query = DB::table('story_translations')->where('story_id', $story->id)->where('target', $data['target'])->where('source_hash', $data['expected_hash'])->where('review_status', 'approved');
            abort_unless($query->update(['status' => 'revoked', 'review_status' => 'revoked', 'updated_at' => now()]), 404);
            $books = DB::table('story_enrollments')->where('story_id', $story->id)->pluck('book_id');
            $chapters = Chapter::whereIn('book_id', $books)->pluck('id');
            DB::table('chapter_translations')->whereIn('chapter_id', $chapters)->where('target', $data['target'])->where('source_hash', $data['expected_hash'])->where('review_status', 'approved')->update(['status' => 'revoked', 'review_status' => 'revoked', 'updated_at' => now()]);
            DB::table('content_audit_events')->insert(['story_id' => $story->id, 'actor_id' => $r->user()->id, 'action' => 'translation_revoked', 'target' => $data['target'], 'source_hash' => $data['expected_hash'], 'created_at' => now()]);
        });

        return back()->with('status', 'Đã thu hồi duyệt bản dịch. Nội dung được giữ trong lịch sử.');
    }

    private function availableTranslations($stories): array
    {
        foreach ($stories as $key => $story) {
            $stories[$key] = app(CatalogueService::class)->publicStory($story);
        }
        $records = DB::table('story_translations')->whereIn('story_id', $stories->pluck('id'))->where('status', 'available')->get()->groupBy('story_id');
        $result = [];
        foreach ($stories as $story) {
            $source = config('learning.sources.'.$story->language.'.code');
            $matching = ($records[$story->id] ?? collect())->filter(fn ($t) => $t->source === $source && $t->source_hash === hash('sha256', $story->body));
            if ($story->relationLoaded('published_chapters') && $story->published_chapters->count() > 1) {
                $chapters = $story->published_chapters;
                $approved = DB::table('story_chapter_translations')->whereIn('story_chapter_id', $chapters->pluck('story_chapter_id'))->where('source', $source)->where('status', 'available')->get();
                $matching = $matching->filter(fn ($translation) => $chapters->every(fn ($chapter) => $approved->contains(fn ($t) => $t->story_chapter_id === $chapter->story_chapter_id && $t->target === $translation->target && $t->source_hash === $chapter->source_hash)));
            }
            $result[$story->id] = $matching;
        }

        return $result;
    }

    public function storyDetail(Request $request, Story $story)
    {
        abort_unless($story->published_at, 404);
        $story = app(CatalogueService::class)->publicStory($story);
        $target = $request->validate(['target' => 'sometimes|in:'.implode(',', array_keys(config('learning.targets')))])['target'] ?? $request->user()?->translation_language ?? config('learning.default_target');
        $enrollment = $request->user() ? DB::table('story_enrollments')->where('user_id', $request->user()->id)->where('story_id', $story->id)->first() : null;
        $chapter = $enrollment ? Chapter::where('user_id', $request->user()->id)->where('book_id', $enrollment->book_id)->orderByRaw('read_count = 0 DESC')->orderBy('id')->first() : null;
        $translations = $this->availableTranslations(collect([$story]))[$story->id];

        return view('saas.story-detail', compact('story', 'target', 'chapter', 'translations'));
    }
}

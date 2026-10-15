<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Services\CatalogueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoryChapterController extends Controller
{
    private function authorizeEditor(Request $r): void
    {
        abort_unless($r->user()->is_admin || $r->user()->role === 'editor', 403);
    }

    public function create(Request $r, Story $story, CatalogueService $service)
    {
        $this->authorizeEditor($r);
        $data = $r->validate(['title' => 'required|string|max:128', 'body' => 'required|string|max:5000']);
        DB::transaction(function () use ($r, $story, $data, $service) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            $service->ensureOriginal($story);
            $position = 1 + (int) StoryChapter::where('story_id', $story->id)->max('position');
            abort_if($position > 100, 422, 'A story supports up to 100 chapters.');
            $chapter = StoryChapter::create($data + ['story_id' => $story->id, 'language' => $story->language, 'position' => $position, 'revision' => 1]);
            $service->recordRevision($chapter, $r->user()->id);
            $service->audit($story, $r->user()->id, 'chapter_created');
        });

        return back()->with('status', 'Đã thêm chương vào bản nháp. Xuất bản phiên bản mới để người học mới đọc.');
    }

    public function update(Request $r, Story $story, StoryChapter $chapter, CatalogueService $service)
    {
        $this->authorizeEditor($r);
        abort_unless($chapter->story_id === $story->id, 404);
        $data = $r->validate(['title' => 'required|string|max:128', 'body' => 'required|string|max:5000', 'expected_revision' => 'required|integer|min:1']);
        DB::transaction(function () use ($r, $story, $chapter, $data, $service) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            $chapter = StoryChapter::whereKey($chapter->id)->lockForUpdate()->firstOrFail();
            abort_unless($chapter->revision === (int) $data['expected_revision'], 409, 'Chapter changed. Reload before editing.');
            $chapter->title = $data['title'];
            $chapter->body = $data['body'];
            $chapter->revision++;
            $chapter->save();
            $service->recordRevision($chapter, $r->user()->id);
            if ($chapter->position === 1) {
                $story->body = $chapter->body;
                $story->save();
            }
            $service->audit($story, $r->user()->id, 'chapter_updated');
        });

        return back()->with('status', 'Đã lưu bản nháp chương; bản xuất bản hiện tại được giữ nguyên.');
    }

    public function move(Request $r, Story $story, StoryChapter $chapter, CatalogueService $service)
    {
        $this->authorizeEditor($r);
        abort_unless($chapter->story_id === $story->id, 404);
        $data = $r->validate(['direction' => 'required|in:up,down']);
        DB::transaction(function () use ($r, $story, $chapter, $data, $service) {
            $story = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            $ids = StoryChapter::where('story_id', $story->id)->orderBy('position')->pluck('id')->all();
            $index = array_search($chapter->id, $ids, true);
            $other = $index + ($data['direction'] === 'up' ? -1 : 1);
            if ($other < 0 || $other >= count($ids)) {
                return;
            }
            [$ids[$index], $ids[$other]] = [$ids[$other], $ids[$index]];
            foreach ($ids as $i => $id) {
                StoryChapter::whereKey($id)->update(['position' => 10001 + $i]);
            }
            foreach ($ids as $i => $id) {
                StoryChapter::whereKey($id)->update(['position' => 1 + $i]);
            }
            $story->body = StoryChapter::findOrFail($ids[0])->body;
            $story->save();
            $service->audit($story, $r->user()->id, 'chapters_reordered');
        });

        return back();
    }

    public function translation(Request $r, Story $story, StoryChapter $chapter, CatalogueService $service)
    {
        $this->authorizeEditor($r);
        abort_unless($chapter->story_id === $story->id, 404);
        $data = $r->validate(['target' => 'required|in:'.implode(',', array_keys(config('learning.targets'))), 'body' => 'required|string|max:30000', 'expected_hash' => 'required|string|size:64']);
        DB::transaction(function () use ($r, $story, $chapter, $data, $service) {
            Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            $chapter = StoryChapter::whereKey($chapter->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(hash('sha256', $chapter->body), $data['expected_hash']), 409, 'Original changed. Reload before approval.');
            DB::table('story_chapter_translations')->updateOrInsert(['story_chapter_id' => $chapter->id, 'source' => config('learning.sources.'.$chapter->language.'.code'), 'target' => $data['target'], 'source_hash' => $data['expected_hash']], ['body' => $data['body'], 'status' => 'available', 'review_status' => 'approved', 'provider' => 'Editorial', 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $service->audit($story, $r->user()->id, 'chapter_translation_approved', $data['target'], $data['expected_hash']);
        });

        return back()->with('status', 'Đã duyệt bản dịch cho đúng phiên bản chương.');
    }

    public function revoke(Request $r, Story $story, StoryChapter $chapter, CatalogueService $service)
    {
        $this->authorizeEditor($r);
        abort_unless($chapter->story_id === $story->id, 404);
        $data = $r->validate(['target' => 'required|in:'.implode(',', array_keys(config('learning.targets'))), 'expected_hash' => 'required|string|size:64']);
        DB::transaction(function () use ($r, $story, $chapter, $data, $service) {
            Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('story_chapter_translations')->where('story_chapter_id', $chapter->id)->where('target', $data['target'])->where('source_hash', $data['expected_hash'])->where('review_status', 'approved');
            abort_unless($query->update(['status' => 'revoked', 'review_status' => 'revoked', 'updated_at' => now()]), 404);
            $revisions = DB::table('story_chapter_revisions')->where('story_chapter_id', $chapter->id)->where('source_hash', $data['expected_hash'])->pluck('id');
            $copies = Chapter::whereIn('story_chapter_revision_id', $revisions)->pluck('id');
            DB::table('chapter_translations')->whereIn('chapter_id', $copies)->where('target', $data['target'])->where('source_hash', $data['expected_hash'])->where('review_status', 'approved')->update(['status' => 'revoked', 'review_status' => 'revoked', 'updated_at' => now()]);
            // Preview caches share the first original's hash, so revoke those matching approvals too.
            DB::table('story_translations')->where('story_id', $story->id)->where('target', $data['target'])->where('source_hash', $data['expected_hash'])->where('review_status', 'approved')->update(['status' => 'revoked', 'review_status' => 'revoked', 'updated_at' => now()]);
            $service->audit($story, $r->user()->id, 'chapter_translation_revoked', $data['target'], $data['expected_hash']);
        });

        return back()->with('status', 'Đã thu hồi duyệt. Bản dịch và lịch sử được giữ.');
    }
}

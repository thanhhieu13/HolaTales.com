<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadingHeartbeatController extends Controller
{
    public function store(Request $r, int $chapter)
    {
        $data = $r->validate(['tab_id' => 'required|uuid', 'source_hash' => 'required|string|size:64', 'active' => 'required|boolean', 'visible' => 'required|boolean']);
        $seconds = DB::transaction(function () use ($r, $chapter, $data) {
            $user = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $content = Chapter::where('user_id', $user->id)->lockForUpdate()->findOrFail($chapter);
            abort_unless($content->language === $user->selected_language, 409, 'Learning source changed. Reload the reader.');
            abort_unless(hash_equals(hash('sha256', $content->raw_text), $data['source_hash']), 409, 'Chapter changed. Reload to continue.');
            $identity = ['user_id' => $user->id, 'tab_id' => $data['tab_id']];
            $previous = DB::table('reading_sessions')->where($identity)->first();
            $now = now('UTC')->startOfSecond();
            $active = $data['active'] && $data['visible'];
            $count = 0;
            if ($previous && $previous->chapter_id === $chapter && $previous->source_hash === $data['source_hash'] && $previous->timezone === $user->learning_timezone && $previous->active && $active) {
                $last = Carbon::parse($previous->last_heartbeat_at, 'UTC');
                if ($now->diffInSeconds($last, true) <= 45) {
                    $clock = $user->reading_clock_at ? Carbon::parse($user->reading_clock_at, 'UTC') : $last;
                    $start = $last->max($clock)->max($now->copy()->subSeconds(30));
                    $count = max(0, (int) $start->diffInSeconds($now, false));
                    if ($count > 0) {
                        // Split at local midnight; keep historical timezone buckets unchanged.
                        $cursor = $now->copy()->subSeconds($count);
                        while ($cursor->lt($now)) {
                            $local = $cursor->copy()->setTimezone($user->learning_timezone);
                            $boundary = $local->copy()->startOfDay()->addDay()->setTimezone('UTC')->min($now);
                            $amount = (int) $cursor->diffInSeconds($boundary, false);
                            $bucket = ['user_id' => $user->id, 'language' => $content->language, 'day' => $local->toDateString(), 'timezone' => $user->learning_timezone];
                            DB::table('reading_activity_days')->insertOrIgnore($bucket + ['seconds' => 0]);
                            DB::table('reading_activity_days')->where($bucket)->increment('seconds', $amount);
                            $cursor = $boundary;
                        }
                    }
                }
            }
            DB::table('reading_sessions')->updateOrInsert($identity, ['chapter_id' => $chapter, 'source_hash' => $data['source_hash'], 'timezone' => $user->learning_timezone, 'active' => $active, 'last_heartbeat_at' => $now]);
            if ($active) {
                $user->reading_clock_at = $now;
                $user->save();
            }

            return $count;
        });

        return response()->json(['counted_seconds' => $seconds]);
    }
}

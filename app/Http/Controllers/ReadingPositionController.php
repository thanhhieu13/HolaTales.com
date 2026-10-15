<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadingPositionController extends Controller
{
    public function show(Request $request, int $chapter)
    {
        $content = Chapter::where('user_id', $request->user()->id)->findOrFail($chapter);
        $hash = hash('sha256', $content->raw_text);
        $position = DB::table('reading_positions')->where('user_id', $request->user()->id)->where('chapter_id', $chapter)->where('source_hash', $hash)->value('scroll_y');

        return response()->json(['source_hash' => $hash, 'scroll_y' => $position ?? 0]);
    }

    public function store(Request $request, int $chapter)
    {
        $data = $request->validate(['source_hash' => 'required|string|size:64', 'scroll_y' => 'required|integer|min:0|max:10000000']);
        DB::transaction(function () use ($request, $chapter, $data) {
            $content = Chapter::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($chapter);
            abort_unless(hash_equals(hash('sha256', $content->raw_text), $data['source_hash']), 409, 'Chapter changed. Reload before saving reading position.');
            $identity = ['user_id' => $request->user()->id, 'chapter_id' => $chapter, 'source_hash' => $data['source_hash']];
            DB::table('reading_positions')->insertOrIgnore($identity + ['scroll_y' => $data['scroll_y'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('reading_positions')->where($identity)->update(['scroll_y' => $data['scroll_y'], 'updated_at' => now()]);
        });

        return response()->json(['saved' => true]);
    }
}

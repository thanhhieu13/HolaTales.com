<?php

use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\User;
use App\Services\CatalogueService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'holatales_qa_20261014') {
    throw new RuntimeException('Local browser QA only.');
}
$editor = User::where('email', 'qa.editor.20261014@example.test')->firstOrFail();
$service = app(CatalogueService::class);
DB::transaction(function () use ($editor, $service) {
    $story = Story::firstOrCreate(['slug' => 'qa-dos-capitulos-20261014'], ['title' => '[QA] Dos capítulos', 'language' => 'spanish', 'level' => 'A1', 'topic' => 'QA fixture', 'summary' => 'Synthetic story for reader resume, chapter order and review QA.', 'body' => implode("\n\n", array_fill(0, 14, 'Ana lee un libro. Lucía visita una panadería. El panadero sonríe. Ana compra pan para su abuela.')), 'glossary' => ['panadería' => 'bakery', 'libro' => 'book'], 'glossary_language' => 'en', 'is_premium' => false]);
    $service->ensureOriginal($story);
    if (StoryChapter::where('story_id', $story->id)->count() === 1) {
        $c = StoryChapter::create(['story_id' => $story->id, 'position' => 2, 'revision' => 1, 'title' => 'No tires la toalla', 'language' => 'spanish', 'body' => 'Ana dice: No tires la toalla. Lucía respira hondo y sigue adelante.']);
        $service->recordRevision($c, $editor->id);
    }
    if (! $story->current_publication_id) {
        $service->publish($story, $editor->id);
    }
    foreach (StoryChapter::where('story_id', $story->id)->get() as $c) {
        $body = $c->position === 1 ? implode("\n\n", array_fill(0, 14, 'Ana reads a book. Lucía visits a bakery. The baker smiles. Ana buys bread for her grandmother.')) : "Ana says: Don't give up. Lucía takes a deep breath and keeps going.";
        DB::table('story_chapter_translations')->updateOrInsert(['story_chapter_id' => $c->id, 'source' => 'es', 'target' => 'en', 'source_hash' => hash('sha256', $c->body)], ['body' => $body, 'provider' => 'Editorial', 'status' => 'available', 'review_status' => 'approved', 'reviewed_by' => $editor->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
    echo 'Synthetic browser fixture story '.$story->id.' prepared with two ordered chapters and editorial idiom correction.'.PHP_EOL;
});

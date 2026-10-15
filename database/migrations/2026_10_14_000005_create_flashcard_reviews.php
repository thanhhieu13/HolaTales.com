<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcard_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('request_id');
            $t->string('kind', 8);
            $t->unsignedBigInteger('item_id');
            $t->string('source');
            $t->string('target', 8);
            $t->string('grade', 8);
            $t->smallInteger('previous_stage');
            $t->smallInteger('stage');
            $t->boolean('relearning');
            $t->date('next_review')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'request_id']);
            $t->index(['user_id', 'source', 'target', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_reviews');
    }
};

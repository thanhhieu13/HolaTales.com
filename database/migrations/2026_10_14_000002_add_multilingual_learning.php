<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', fn (Blueprint $t) => $t->string('glossary_language')->default('vi'));
        Schema::create('saved_meanings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16);
            $t->unsignedBigInteger('item_id');
            $t->string('target', 8);
            $t->text('translation');
            $t->unique(['user_id', 'kind', 'item_id', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_meanings');
        Schema::table('stories', fn (Blueprint $t) => $t->dropColumn('glossary_language'));
    }
};

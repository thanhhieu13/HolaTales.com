<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_meanings', function (Blueprint $t) {
            $t->integer('stage')->nullable();
            $t->date('next_review')->nullable();
            $t->date('added_to_srs')->nullable();
            $t->boolean('relearning')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('saved_meanings', fn (Blueprint $t) => $t->dropColumn(['stage', 'next_review', 'added_to_srs', 'relearning']));
    }
};

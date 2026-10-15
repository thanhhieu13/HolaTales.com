<?php

use Illuminate\Database\Migrations\Migration;

class ModifyEncounteredWordsTable7 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE encountered_words DROP COLUMN example_sentence');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

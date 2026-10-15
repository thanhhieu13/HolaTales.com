<?php

use App\Models\User;
use App\Services\GoalService;
use Illuminate\Database\Migrations\Migration;

class ResetSelectedLanguage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $goalService = new GoalService;
        $users = User::get();

        foreach ($users as $user) {
            $user->selected_language = 'spanish';
            $user->save();

            $goalService->createGoalsForLanguage($user->id, 'spanish');
        }
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

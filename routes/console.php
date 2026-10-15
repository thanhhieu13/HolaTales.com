<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

if (env('BACKUP_INTERVAL')) {
    Schedule::command('app:create-backup')->cron(env('BACKUP_INTERVAL'));
}
Artisan::command('holatales:role {email} {role}', function () {
    $role = $this->argument('role');
    if (! in_array($role, ['learner', 'editor', 'admin'], true)) {
        $this->error('Role must be learner, editor or admin.');

        return 1;
    }
    $u = User::where('email', $this->argument('email'))->firstOrFail();
    if ($u->is_admin && $role !== 'admin' && ! User::where('is_admin', 1)->where('id', '!=', $u->id)->exists()) {
        $this->error('Cannot remove the last administrator.');

        return 1;
    }
    $u->role = $role;
    $u->is_admin = $role === 'admin';
    $u->save();
    $this->info('Role updated.');
});

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('expo:staff {email} {--name=}', function (string $email) {
    $validator = Illuminate\Support\Facades\Validator::make(['email' => $email], ['email' => ['required', 'email']]);
    if ($validator->fails()) {
        $this->error('Please pass a valid email address.');

        return 1;
    }

    $password = $this->secret('Password (min 12 characters)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Password must be at least 12 characters.');

        return 1;
    }

    App\Models\User::query()->updateOrCreate(
        ['email' => Illuminate\Support\Str::lower($email)],
        ['name' => $this->option('name') ?: Illuminate\Support\Str::before($email, '@'), 'password' => $password],
    );

    $this->info("Staff account ready for {$email}.");

    return 0;
})->purpose('Create or reset a staff login for the visitor desk');

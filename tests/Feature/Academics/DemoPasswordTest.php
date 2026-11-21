<?php

use App\Models\User;
use Database\Seeders\AcademicDemoSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Support\Facades\Hash;

// DEMO_PASSWORD (config demo.password): every synthetic demo account gets it when the
// demo seeders run, so a demo reachable through a tunnel does not use the well known default.

it('uses the documented default in tests, whatever the developer has in .env', function () {
    expect(config('demo.password'))->toBe('password');
});

it('gives every demo account the configured password, and applies a new one on the next run', function () {
    app()['env'] = 'local';

    config(['demo.password' => 'first-demo-pass']);
    $this->seed(AcademicDemoSeeder::class);

    $emails = ['super_admin@demo.test', 'head_of_academics@demo.test', 'lecturer@demo.test', 'student@demo.test', 'first-login@demo.test', 'lecturer.2@demo.test', 'student.001@demo.test'];

    foreach ($emails as $email) {
        expect(Hash::check('first-demo-pass', User::where('email', $email)->firstOrFail()->password))->toBeTrue($email);
    }

    // An account that already exists is moved to the new password, not left on the old one.
    config(['demo.password' => 'second-demo-pass']);
    $this->seed(AcademicDemoSeeder::class);

    foreach ($emails as $email) {
        $user = User::where('email', $email)->firstOrFail();
        expect(Hash::check('second-demo-pass', $user->password))->toBeTrue($email)
            ->and(Hash::check('first-demo-pass', $user->password))->toBeFalse($email);
    }

    // The forced password change is put back on first-login, so the demo can be repeated.
    expect(User::where('email', 'first-login@demo.test')->firstOrFail()->must_change_password)->toBeTrue();
});

it('refuses a demo password shorter than 8 characters', function () {
    app()['env'] = 'local';
    config(['demo.password' => 'short']);

    expect(fn () => $this->seed(DemoUsersSeeder::class))->toThrow(RuntimeException::class, 'at least 8 characters');
});

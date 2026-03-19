<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use Illuminate\Validation\ValidationException;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('new:client', function () {
    $this->info('Creating a new admin user...');

    $name = $this->ask('Name');
    $email = $this->ask('Email');

    // Collect password + confirmation and validate password rules.
    $password = null;
    while (true) {
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $passwordValidator = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            // `confirmed` checks `password_confirmation`.
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($passwordValidator->fails()) {
            $this->error('Password does not meet requirements:');
            foreach ($passwordValidator->errors()->all() as $message) {
                $this->line('- ' . $message);
            }
            $this->line('');
            continue;
        }

        break;
    }

    // Validate profile + uniqueness before creating the user.
    try {
        Validator::make([
            'name' => $name,
            'email' => $email,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
        ])->validate();
    } catch (ValidationException $e) {
        $this->error('Invalid input:');
        foreach ($e->errors() as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $this->line('- ' . $message);
            }
        }
        return 1;
    }

    $user = User::create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => User::ROLE_ADMIN,
    ]);

    $this->info('User created successfully: ' . $user->email);
    return 0;
})->purpose('Create a new user with admin role');

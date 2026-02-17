<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Create user
$user = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'is_active' => true,
]);

echo "✅ User created successfully!\n";
echo "Email: " . $user->email . "\n";
echo "Name: " . $user->name . "\n";
echo "Role: " . $user->role . "\n";

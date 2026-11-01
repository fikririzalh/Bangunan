<?php

use App\Models\User;

// ---------- Tamu harus login dulu ----------

test('guest is redirected to login from admin pages', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    'dashboard' => '/dashboard',
    'admin profile' => '/admin/profile',
]);

test('guest cannot change password', function () {
    $this->post(route('admin.password.update'), [
        'old_password' => 'password',
        'new_password' => 'PasswordBaru-2026',
        'new_password_confirmation' => 'PasswordBaru-2026',
    ])->assertRedirect(route('login'));
});

// ---------- Halaman profil admin ----------

test('logged in user sees own data on the admin profile page', function () {
    $user = User::factory()->create([
        'name' => 'Budi Pemilik',
        'email' => 'budi@example.com',
        'phone' => '08123456789',
    ]);

    $this->actingAs($user)
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSee('Budi Pemilik')
        ->assertSee('budi@example.com')
        ->assertSee('08123456789');
});

// ---------- Header (dipakai di semua halaman admin) ----------

test('header shows the name of the logged in user', function () {
    $user = User::factory()->create(['name' => 'Budi Pemilik']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Budi Pemilik');
});

test('header shows placeholder image when user has no photo', function () {
    $user = User::factory()->create(['photo' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('upload/no_image.jpg');
});

test('header shows the uploaded photo when user has one', function () {
    $user = User::factory()->create(['photo' => 'foto-contoh.jpg']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('upload/user_images/foto-contoh.jpg');
});
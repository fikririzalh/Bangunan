<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ---------- Ganti password dari halaman profil admin ----------

test('weak new password is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.password.update'), [
            'old_password' => 'password',
            'new_password' => 'abc',
            'new_password_confirmation' => 'abc',
        ])
        ->assertSessionHasErrors('new_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('new password must match its confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.password.update'), [
            'old_password' => 'password',
            'new_password' => 'PasswordBaru-2026',
            'new_password_confirmation' => 'BedaSendiri-2026',
        ])
        ->assertSessionHasErrors('new_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('wrong old password does not change the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.password.update'), [
            'old_password' => 'salah-total',
            'new_password' => 'PasswordBaru-2026',
            'new_password_confirmation' => 'PasswordBaru-2026',
        ])
        ->assertSessionHas('alert-type', 'error');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('strong new password is saved and user is logged out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.password.update'), [
            'old_password' => 'password',
            'new_password' => 'PasswordBaru-2026',
            'new_password_confirmation' => 'PasswordBaru-2026',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Hash::check('PasswordBaru-2026', $user->fresh()->password))->toBeTrue();
});

// ---------- Logout harus POST ----------

test('logout cannot be triggered with a GET request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/logout')
        ->assertStatus(405);

    $this->assertAuthenticatedAs($user);
});

test('admin can logout with a POST request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.logout'))
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('dashboard renders logout as a POST form with csrf token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<form method="POST" action="'.route('admin.logout').'">', false)
        ->assertSee('name="_token"', false);
});

// ---------- Mass assignment ----------

test('role cannot be set through mass assignment', function () {
    $user = User::create([
        'name' => 'Penyusup',
        'email' => 'penyusup@example.com',
        'password' => 'PasswordBaru-2026',
        'role' => 'owner',
    ]);

    expect($user->fresh()->role)->toBe('user');
});

test('profile fields can still be mass assigned', function () {
    $user = User::create([
        'name' => 'Pemilik Toko',
        'email' => 'pemilik@example.com',
        'password' => 'PasswordBaru-2026',
        'phone' => '08123456789',
        'address' => 'Jl. Contoh No. 1',
    ]);

    expect($user->fresh())
        ->name->toBe('Pemilik Toko')
        ->phone->toBe('08123456789')
        ->address->toBe('Jl. Contoh No. 1');
});

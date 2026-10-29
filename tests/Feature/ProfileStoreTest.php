<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->uploadDir = public_path('upload/user_images');
    $this->filesBefore = is_dir($this->uploadDir) ? scandir($this->uploadDir) : [];
});

afterEach(function () {
    // Hapus file yang dibuat oleh tes ini saja, file lama tidak disentuh.
    if (is_dir($this->uploadDir)) {
        foreach (array_diff(scandir($this->uploadDir), $this->filesBefore) as $file) {
            @unlink($this->uploadDir.'/'.$file);
        }
    }
});

function validProfileData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Pemilik Toko',
        'email' => 'pemilik@example.com',
        'phone' => '08123456789',
        'address' => 'Jl. Contoh No. 1',
    ], $overrides);
}

test('guest cannot update profile', function () {
    $this->post(route('profile.store'), validProfileData())
        ->assertRedirect(route('login'));
});

test('profile can be updated without changing photo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData())
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Pemilik Toko')
        ->email->toBe('pemilik@example.com')
        ->phone->toBe('08123456789');
});

test('valid image is stored with a server generated filename', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData([
            'photo' => UploadedFile::fake()->image('foto-saya.png', 200, 200),
        ]))
        ->assertSessionHasNoErrors();

    $photo = $user->fresh()->photo;

    expect($photo)->not->toBeNull()
        ->and($photo)->not->toContain('foto-saya')
        ->and($photo)->toMatch('/\.(jpg|jpeg|png|webp)$/')
        ->and(file_exists($this->uploadDir.'/'.$photo))->toBeTrue();
});

test('php file is rejected as photo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData([
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ]))
        ->assertSessionHasErrors('photo');

    expect($user->fresh()->photo)->toBeNull()
        ->and(array_diff(scandir($this->uploadDir), $this->filesBefore))->toBeEmpty();
});

test('php code disguised as jpg is rejected', function () {
    $user = User::factory()->create();

    // File sungguhan (bukan fake) supaya tipe file dibaca dari ISI-nya, bukan dari nama.
    $path = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($path, '<?php echo "hacked"; ?>');
    $disguised = new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData(['photo' => $disguised]))
        ->assertSessionHasErrors('photo');

    expect($user->fresh()->photo)->toBeNull();

    @unlink($path);
});

test('photo larger than 2 MB is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData([
            'photo' => UploadedFile::fake()->image('besar.jpg')->size(3000),
        ]))
        ->assertSessionHasErrors('photo');
});

test('name and email are required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData(['name' => '', 'email' => '']))
        ->assertSessionHasErrors(['name', 'email']);
});

test('email must be unique but user can keep their own email', function () {
    User::factory()->create(['email' => 'dipakai@example.com']);
    $user = User::factory()->create(['email' => 'saya@example.com']);

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData(['email' => 'dipakai@example.com']))
        ->assertSessionHasErrors('email');

    $this->actingAs($user)
        ->post(route('profile.store'), validProfileData(['email' => 'saya@example.com']))
        ->assertSessionHasNoErrors();
});

test('validation error is shown on the profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.profile'))
        ->followingRedirects()
        ->post(route('profile.store'), validProfileData([
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ]))
        ->assertOk()
        ->assertSee('The photo field must be an image.');
});

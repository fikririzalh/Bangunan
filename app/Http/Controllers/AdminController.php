<?php

namespace App\Http\Controllers;

// use App\Http\Controllers\auth;

use App\Mail\VerificationCodeEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function AdminLogout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }

    // End Method

    public function AdminLogin(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            $verificationCode = random_int(100000, 999999);

            session(['verification_code' => $verificationCode, 'user_id' => $user->id]);

            Mail::to($user->email)->send(new VerificationCodeEmail($verificationCode));

            Auth::logout();

            return redirect()->route('custom.verification.form')->with('status', 'Verification code send to your email');
        }

        return redirect()->back()->withErrors(['email' => 'Invalid Credentials Povided']);
    }

    // End Method

    public function ShowVerification()
    {
        return view('auth.verify');
    }

    // End Method

    public function VerificationVerify(Request $request)
    {
        $request->validate(['code' => 'required|numeric']);

        if ($request->code == session('verification_code')) {
            Auth::loginUsingId(session('user_id'));

            session()->forget(['verification_code', 'user_id']);

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['code' => 'Invalid Verification Code']);
    }

    // End Method

    public function AdminProfile()
    {
        $id = Auth::user()->id;
        $profileData = User::find($id); // penambahan $id

        return view('admin.admin_profile', compact('profileData'));

    }

    // End Method\

    public function ProfileStore(Request $request)
    {

        $id = Auth::user()->id;
        $data = User::find($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data->name = $validated['name'];
        $data->email = $validated['email'];
        $data->phone = $validated['phone'] ?? null;
        $data->address = $validated['address'] ?? null;

        $oldPhotoPath = $data->photo;

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            // Nama file dibuat server (acak + ekstensi dari isi file), bukan dari nama kiriman pengguna.
            $filename = $file->hashName();
            $file->move(public_path('upload/user_images'), $filename);
            $data->photo = $filename;

            if ($oldPhotoPath && $oldPhotoPath !== $filename) {
                $this->deleteOldImage($oldPhotoPath);
            }
        }

        $data->save();

        $notification = [
            'message' => 'Profile Updated Successfully',
            'alert-type' => 'success',
        ];

        return redirect()->back()->with($notification);

    }

    // End Method

    private function deleteOldImage(string $oldPhotoPath): void
    {
        $fullPath = public_path('upload/user_images/'.$oldPhotoPath);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function PasswordUpdate(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        if (! Hash::check($request->old_password, $user->password)) {
            $notification = [
                'message' => 'Old Password does not match!',
                'alert-type' => 'error',
            ];

            return back()->with($notification);

        }

        User::whereId($user->id)->update([
            'password' => Hash::make($request->new_password),
        ]);

        Auth::logout();

        $notification = [
            'message' => 'Password Updated Successfully',
            'alert-type' => 'success',
        ];

        return redirect()->route('login')->with($notification);

    }

    // End Private Method

    }

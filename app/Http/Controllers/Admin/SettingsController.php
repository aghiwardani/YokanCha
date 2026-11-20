<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin settings: shop details shown to buyers (WhatsApp, bank transfer,
 * bottles per order) and the signed-in admin's own account.
 */
class SettingsController extends Controller
{
    public function show(): View
    {
        return view('admin.settings.show', [
            'shop' => [
                'whatsapp' => config('yokancha.whatsapp'),
                'bank_name' => config('yokancha.payment.bank'),
                'bank_account' => config('yokancha.payment.account_number'),
                'bank_holder' => config('yokancha.payment.account_name'),
                'max_per_variant' => config('yokancha.max_per_variant'),
            ],
            'user' => Auth::user(),
        ]);
    }

    public function updateShop(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('shop', [
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[\d\s+().-]+$/'],
            'bank_name' => ['required', 'string', 'max:50'],
            'bank_account' => ['required', 'string', 'max:40', 'regex:/^[\d\s.-]+$/'],
            'bank_holder' => ['required', 'string', 'max:100'],
            'max_per_variant' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka.',
            'bank_account.regex' => 'Nomor rekening hanya boleh berisi angka.',
        ], [
            'whatsapp' => 'nomor WhatsApp',
            'bank_name' => 'nama bank',
            'bank_account' => 'nomor rekening',
            'bank_holder' => 'nama pemilik rekening',
            'max_per_variant' => 'batas botol per pesanan',
        ]);

        if (! Phone::looksValid($data['whatsapp'])) {
            throw ValidationException::withMessages(['whatsapp' => 'Nomor WhatsApp belum benar, contoh 081234567890.'])
                ->errorBag('shop');
        }

        Setting::store([
            // wa.me needs the international form (62812...).
            'whatsapp' => Phone::normalize($data['whatsapp']),
            'bank_name' => trim($data['bank_name']),
            'bank_account' => preg_replace('/\s+/', '', $data['bank_account']),
            'bank_holder' => trim($data['bank_holder']),
            'max_per_variant' => (int) $data['max_per_variant'],
        ]);

        return redirect()->route('admin.settings')->with('status', 'Info toko disimpan.');
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('account', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Any change to the account needs the current password.
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Masukkan password sekarang untuk menyimpan perubahan.',
            'current_password.current_password' => 'Password sekarang salah.',
            'password.confirmed' => 'Ulangi password baru dengan sama persis.',
        ], [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'password baru',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $changedPassword = filled($data['password'] ?? null);
        if ($changedPassword) {
            $user->password = $data['password'];
        }
        $user->save();

        if ($changedPassword) {
            // Sign out other browsers that still use the old password.
            Auth::logoutOtherDevices($data['password']);
            $request->session()->regenerate();
        }

        return redirect()->route('admin.settings')
            ->with('status', $changedPassword ? 'Akun disimpan. Password baru sudah aktif.' : 'Akun disimpan.');
    }
}

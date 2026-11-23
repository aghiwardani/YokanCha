<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Reset a forgotten admin password from the server:
 *   sudo -u deploy php artisan admin:reset-password
 * The new password is typed hidden and never lands in the shell history.
 */
class AdminResetPassword extends Command
{
    protected $signature = 'admin:reset-password {--email= : Email of the admin account, when there is more than one}';

    protected $description = 'Set a new password for an admin account (typed hidden, not saved in shell history)';

    public function handle(): int
    {
        $admins = User::where('is_admin', true)->orderBy('id')->get();

        if ($admins->isEmpty()) {
            $this->error('Belum ada akun admin. Jalankan: php artisan db:seed --class=AdminUserSeeder');

            return self::FAILURE;
        }

        if ($email = $this->option('email')) {
            $admin = $admins->first(fn (User $u) => strcasecmp($u->email, $email) === 0);
            if (! $admin) {
                $this->error("Tidak ada akun admin dengan email {$email}.");

                return self::FAILURE;
            }
        } elseif ($admins->count() === 1) {
            $admin = $admins->first();
        } else {
            $email = $this->choice('Akun admin yang mana?', $admins->pluck('email')->all(), 0);
            $admin = $admins->firstWhere('email', $email);
        }

        $this->info("Akun admin: {$admin->name} <{$admin->email}>");

        for ($try = 1; $try <= 3; $try++) {
            $password = (string) $this->secret('Password baru (minimal 8 karakter, tidak terlihat saat diketik)');
            $confirm = (string) $this->secret('Ulangi password baru');

            $validator = Validator::make(
                ['password' => $password, 'password_confirmation' => $confirm],
                ['password' => ['required', 'confirmed', Password::min(8)]],
                [
                    'password.required' => 'Password baru belum diisi.',
                    'password.confirmed' => 'Kedua password tidak sama.',
                    'password.min' => 'Password minimal 8 karakter.',
                ],
            );

            if ($validator->passes()) {
                $admin->password = $password; // hashed by the model cast
                $admin->save();

                // Sign the account out everywhere it is still logged in.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $admin->id)->delete();
                }

                $this->info('Password baru tersimpan. Login di '.url('/admin/login'));

                return self::SUCCESS;
            }

            $this->error($validator->errors()->first('password'));
        }

        $this->error('Dibatalkan setelah 3 kali percobaan. Password tidak diubah.');

        return self::FAILURE;
    }
}

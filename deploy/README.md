# Deploy YokanCha ke VPS

Alur: setiap push ke branch `main`, GitHub Actions menjalankan tes. Kalau lulus, Actions
membangun aplikasi (Composer tanpa paket dev + build CSS/JS), mengirimnya ke VPS lewat
SSH (`rsync`, user `deploy`), lalu menjalankan `deploy/after-deploy.sh` di VPS (migrasi,
cache, reload PHP). Push ke branch lain dan pull request hanya menjalankan tes.

File di VPS yang **tidak pernah ditimpa** saat deploy: `.env`, `storage/`, cache di
`bootstrap/cache/`.

## Sekali saja: siapkan VPS

1. Jalankan `deploy/provision.sh` di VPS (`sudo bash provision.sh`). Ini memasang Nginx,
   PHP 8.4+, MySQL, Composer, Certbot, firewall, user `deploy`, dan folder
   `/var/www/yokancha`. Data login database tersimpan di `/root/yokancha-db.txt`.

2. Buat kunci SSH khusus deploy **di komputermu** (tanpa passphrase, karena dipakai mesin):

   ```powershell
   ssh-keygen -t ed25519 -C "github-actions-yokancha" -N '""' -f $env:USERPROFILE\.ssh\yokancha_deploy
   ```

   Pasang public key-nya ke user `deploy` di VPS:

   ```powershell
   Get-Content $env:USERPROFILE\.ssh\yokancha_deploy.pub | ssh YokanCha@103.55.38.39 "sudo tee -a /home/deploy/.ssh/authorized_keys"
   ```

3. Buat file `.env` di VPS (sekali saja, isi password database dari `/root/yokancha-db.txt`):

   ```bash
   sudo -u deploy nano /var/www/yokancha/.env
   ```

   ```dotenv
   APP_NAME=YokanCha
   APP_ENV=production
   APP_KEY=
   APP_DEBUG=false
   APP_TIMEZONE=Asia/Jakarta
   APP_URL=http://103.55.38.39
   APP_LOCALE=id

   LOG_CHANNEL=daily
   LOG_LEVEL=warning

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=yokancha
   DB_USERNAME=yokancha
   DB_PASSWORD=isi-dari-yokancha-db.txt

   SESSION_DRIVER=database
   SESSION_LIFETIME=120
   CACHE_STORE=database
   QUEUE_CONNECTION=database

   YOKANCHA_WHATSAPP=62812xxxxxxx
   YOKANCHA_BANK_NAME=BCA
   YOKANCHA_BANK_ACCOUNT=1234567890
   YOKANCHA_BANK_HOLDER=YokanCha
   ```

   `APP_KEY` dibiarkan kosong; deploy pertama mengisinya otomatis.

4. Isi secrets di GitHub: **Settings → Secrets and variables → Actions → New repository secret**

   | Nama | Isi |
   |---|---|
   | `SSH_HOST` | `103.55.38.39` |
   | `SSH_USER` | `deploy` |
   | `SSH_PRIVATE_KEY` | isi file `~/.ssh/yokancha_deploy` (private key, semua baris) |
   | `SSH_KNOWN_HOSTS` | hasil `ssh-keyscan -t ed25519 103.55.38.39` |

## Deploy

Push (atau merge) ke `main`. Lihat prosesnya di tab **Actions** di GitHub.

Setelah deploy pertama berhasil, isi data awal (akun admin + master data) **sekali**:

```bash
ssh YokanCha@103.55.38.39
cd /var/www/yokancha && sudo -u deploy php artisan db:seed --force
```

Login admin: `admin@yokancha.local` / `ChangeMe123!` — langsung ganti passwordnya.

## HTTPS (setelah punya domain)

Arahkan DNS domain (record A) ke IP VPS, ubah `server_name _;` di
`/etc/nginx/sites-available/yokancha` jadi nama domainnya, lalu:

```bash
sudo certbot --nginx -d domainmu.com
```

Ubah juga `APP_URL` di `.env` menjadi `https://domainmu.com` dan deploy ulang (atau
jalankan `php artisan config:cache` sebagai user deploy).

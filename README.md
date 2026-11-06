# Enro — do tasks & earn

| Folder | What it is |
|---|---|
| `mobile/` | React Native app (Expo SDK 58, Expo Router, TypeScript) |
| `backend/` | Plain PHP 8 + MySQL. Serves the REST API at `/api` and the admin panel at `/admin` |
| `prototype/` | The original clickable design mock-up (no real data) |

**How users earn**
- **Tasks** (watch ad, visit link, social, daily check-in) pay **coins**, which are redeemed for **airtime or data** (VTpass).
- **Special tasks** pay **real cash (₦)**. The user uploads proof, an admin approves it, and the cash is withdrawn to a bank (Paystack).
- **Adsterra:** banner, native, full-screen ad after every N task claims, and a Smartlink for "watch" tasks. Everything is controlled from Admin → Adsterra ads.

---

## Run it locally (Windows + XAMPP)

**1. Backend**

```bash
cd backend
copy config.example.php config.php        # then set a jwt_secret (php -r "echo bin2hex(random_bytes(32));")
C:\xampp\php\php.exe bin\install.php        # creates the `enro` database, tables and starter data
C:\xampp\php\php.exe bin\create_admin.php "Your Name" you@email.com
C:\xampp\php\php.exe -S 0.0.0.0:8080 router.php
```

Admin panel: http://localhost:8080/admin/

> Port 8000 is already used by another app on this PC, so Enro uses **8080**.

**2. Mobile app**

```bash
cd mobile
npm install
npx expo start
```

- Create `mobile/.env` from `.env.example`. On a real phone, set `EXPO_PUBLIC_API_URL=http://<your-PC-LAN-IP>:8080`. The phone and PC must be on the same Wi-Fi.
- Press `w` for a browser preview. Ads and WebViews only show as placeholders on web.
- **Expo Go vs. dev build:** `react-native-webview` is included in Expo Go, so Expo Go works for testing. For release, build with EAS (see below).

While `debug` is `true` in `config.php`, OTP codes appear in the app ("Test mode: your code is …") and in `backend/storage/logs/sms.log`, so you can test without SMS.

---

## Deploy the backend (cPanel / any PHP host)

1. Create a MySQL database and user in cPanel.
2. Upload the `backend/` folder and point a subdomain (e.g. `api.yourdomain.com`) at it. `.htaccess` routes `/api/*` and blocks private folders.
3. Create `config.php` on the server with the live DB details, a new `jwt_secret`, `debug => false`, and `app_url` set to `https://api.yourdomain.com`.
4. Run `php bin/install.php` and `php bin/create_admin.php …` (via cPanel Terminal, or import `database/schema.sql` + `seed.sql` in phpMyAdmin).
5. **HTTPS is required.** Android blocks plain-HTTP API calls in release builds.

## Build the Android app

```bash
cd mobile
npx eas-cli@latest build -p android --profile preview    # APK you can install/share
npx eas-cli@latest build -p android --profile production # AAB for Play Store
```

Set `EXPO_PUBLIC_API_URL=https://api.yourdomain.com` for the build (in `eas.json` env or EAS environment variables).

---

## Go-live checklist

- [ ] `debug => false` in `config.php` (otherwise OTP codes are exposed in API responses)
- [ ] **Termii** API key + approved sender ID, so OTP SMS are actually sent
- [ ] **VTpass** live keys + correct `variation_code` for every data plan (Admin → Data plans). Test in sandbox first.
- [ ] **Paystack** secret key + the transfers feature enabled on your account. Webhook URL: `https://api.yourdomain.com/api/webhooks/paystack`
- [ ] Adsterra codes pasted in Admin → Adsterra ads; Smartlink set
- [ ] Reward rates in Admin → Settings make sense vs. your Adsterra earnings per user
- [ ] Remove the local test accounts, or reinstall on a clean database
- [ ] Privacy policy & terms pages (needed for Play Store)

**Play Store warning:** apps that pay users for watching ads or installing other apps are often rejected. Many earn-apps also share the APK directly. Read Google Play's policy before you submit.

---

## API overview

Logged-in endpoints need `Authorization: Bearer <token>`.

| | |
|---|---|
| Auth | `POST /auth/register`, `/auth/verify-otp`, `/auth/resend-otp`, `/auth/login`, `/auth/forgot-password`, `/auth/reset-password` |
| Account | `GET /me`, `PUT /me`, `POST /me/password`, `GET /notifications`, `POST /notifications/read-all`, `GET /referrals`, `GET /transactions?currency=coin\|cash` |
| Tasks | `GET /tasks`, `POST /tasks/{id}/start`, `POST /tasks/{id}/claim` (server checks the timer and daily limits) |
| Special | `GET /special-tasks`, `GET /special-tasks/{id}`, `POST /special-tasks/{id}/submit` (multipart `proof` + `note`) |
| Wallet | `GET /wallet/options`, `POST /wallet/redeem`, `POST /wallet/resolve-account`, `POST /wallet/withdraw` |
| Public | `GET /config` (rates + ad settings), `POST /webhooks/paystack` |

Every balance change is written to the `transactions` ledger inside a database transaction. Rejected or failed payouts are refunded automatically.

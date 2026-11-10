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

While `debug` is `true` in `config.php`, verification codes appear in the app ("Test mode: your code is …") and in `backend/storage/logs/mail.log`, so you can test without a mail server.

---

## Deploy the backend (cPanel / any PHP host)

Live backend: **https://enro.truszedproperties.com**. Full steps are in [`backend/DEPLOY.txt`](backend/DEPLOY.txt), which is also inside the zip.

1. Create a MySQL database and user in cPanel.
2. Upload and extract the backend zip in the domain's folder.
3. Open `/setup.php`. It writes `config.php` (debug off, random secret), creates the tables and creates your admin.
4. Add the cron job `php …/bin/cron.php` every 5 minutes. It sends reminders and broadcasts.
5. **Updates:** extract the new zip over the old files, then open `/setup.php` as admin → "Run database upgrade".

Build the zip: `powershell -File backend/bin/make-zip.ps1` → `dist/enro-backend.zip`.

## Push notifications

- The backend sends straight to **Firebase Cloud Messaging**. No Expo account is needed for push.
- **Automatic pushes:**
  - proof approved/rejected, payout paid/refunded, referral bonus
  - a morning "tasks ready" reminder
  - an evening "check in / keep your streak" reminder
  - new tasks, when "Notify users" is ticked
  - admin messages from Admin → Notifications
- **Without Firebase:** messages still show in the app's notification list, and the app schedules its own evening check-in reminder on the phone.

To turn on Firebase:
1. Firebase console → create a project → **add an Android app** with package `app.enro.mobile` → download `google-services.json` into `mobile/`. Then rebuild the APK.
2. Project settings → Service accounts → **Generate new private key** → upload it as `backend/storage/firebase-service-account.json` on the server.

## Build the Android APK

Release builds are signed with Enro's own key in `mobile/credentials/`, which git ignores. **Back up that folder.** Every future APK update must be signed with the same key, or phones will refuse to install it over the old app.

**Cloud build (recommended on a slow connection).** It uploads a few MB, builds on Expo's servers, and gives you a download link:

```bash
cd mobile
npx eas-cli@latest login
npx eas-cli@latest build -p android --profile preview
```

**Local build.** Needs Android Studio and roughly 2 GB of downloads the first time:

```bash
cd mobile
npx expo prebuild -p android --clean
cd android
./gradlew assembleRelease     # → android/app/build/outputs/apk/release/app-release.apk
```

The API URL for builds is `https://enro.truszedproperties.com`. It's set in `eas.json`, `.env.production` and the EAS environment variable `EXPO_PUBLIC_API_URL`.

## Ship app updates without a new APK (OTA)

Builds now include **EAS Update**. Changes to screens, text, logic, colours or images go straight to installed phones:

```bash
cd mobile
npm run update -- --message "Fix wallet text"
```

- Phones download the update in the background. It applies the next time the app opens, or when the user comes back after 5+ minutes away.
- The update uses the same server URL as the build.

**When you still need a new APK:**
- adding a package with native code
- changing permissions, the app icon or splash, `app.json` plugins, or `google-services.json`

For those, raise `version` in `app.json` (e.g. 1.0.0 → 1.1.0) and build again. Updates only reach APKs with the same version, so an update never lands on an APK that's missing the native parts it needs.

## Email verification codes

Sign-up and password-reset codes are emailed with **PHPMailer** (bundled in `backend/lib/PHPMailer`, v7.1.1). Configure it in **Admin → Settings → Email**:
- **Hosting mail:** works with no setup, but often lands in spam.
- **SMTP (recommended):** create a mailbox like `no-reply@truszedproperties.com` in cPanel → Email Accounts. Its "Connect Devices" page shows the host, port (465 SSL) and username.

---

## Go-live checklist

- [ ] `debug => false` in `config.php` (otherwise verification codes are exposed in API responses)
- [ ] **Email**: set up SMTP in Admin → Settings, press **Send test**, and check it lands in the inbox, not spam
- [ ] **VTpass** live keys + correct `variation_code` for every data plan (Admin → Data plans). Test in sandbox first.
- [ ] **Paystack** secret key + the transfers feature enabled on your account. Webhook URL: `https://enro.truszedproperties.com/api/webhooks/paystack`
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

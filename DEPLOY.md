# Deploying to Vercel (free) with a TiDB Cloud database

This repo is already prepared: `vercel.json`, `api/index.php`, `.vercelignore`, and the code
changes for a read-only server (storage in `/tmp`, logs to stderr, HTTPS, Firebase key from an env var).

Never put passwords or keys in git. Everything secret goes in **Vercel → Project → Settings → Environment Variables**.

## 1. Create the tables in TiDB (once, from your PC)

TiDB needs a secure (TLS) connection. Download the certificate once:
<https://letsencrypt.org/certs/isrgrootx1.pem> → save as `C:\certs\isrgrootx1.pem`.

PowerShell, in the project folder (these variables apply to this window only; your `.env` is not changed):

```powershell
$env:DB_CONNECTION="mysql"
$env:DB_HOST="<TiDB host>"
$env:DB_PORT="4000"
$env:DB_DATABASE="laundry"
$env:DB_USERNAME="<TiDB username, like 2abc123.root>"
$env:DB_PASSWORD="<TiDB password>"
$env:MYSQL_ATTR_SSL_CA="C:\certs\isrgrootx1.pem"

php artisan migrate --force
```

## 2. Copy your current data (customers, orders, services, inventory…)

Skip this if you want a clean, empty system (then create the admin in step 3b instead).

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --no-create-info --complete-insert `
  --ignore-table=ssk_laba_dami.sessions --ignore-table=ssk_laba_dami.cache `
  --ignore-table=ssk_laba_dami.cache_locks --ignore-table=ssk_laba_dami.migrations `
  --ignore-table=ssk_laba_dami.jobs --ignore-table=ssk_laba_dami.job_batches `
  --ignore-table=ssk_laba_dami.failed_jobs --ignore-table=ssk_laba_dami.password_reset_tokens `
  ssk_laba_dami > data.sql

& "C:\xampp\mysql\bin\mysql.exe" --ssl-ca="C:\certs\isrgrootx1.pem" -h <TiDB host> -P 4000 `
  -u "<TiDB username>" -p laundry < data.sql
```

Delete `data.sql` afterwards (it contains customer data). It is not tracked by git, but do not leave it lying around.

## 3. Make a strong admin password on the online database

a) If you copied data: your local admin password hash came along. Replace it (keep the `$env:DB_*` variables from step 1 set):

```powershell
php artisan tinker --execute '$u = App\Models\User::where("role","admin")->first(); $u->password = "PUT-A-LONG-STRONG-PASSWORD-HERE"; $u->save(); echo $u->email;'
```

b) If you skipped step 2 (empty database): create the first admin:

```powershell
php artisan tinker --execute '$u = new App\Models\User(["name"=>"Owner","email"=>"you@example.com","password"=>"PUT-A-LONG-STRONG-PASSWORD-HERE"]); $u->forceFill(["role"=>"admin","email_verified_at"=>now()])->save();'
```
(you will still need to add services, inventory categories, machines and baskets in the app).

## 4. Put the code on GitHub

```bash
git add -A
git commit -m "Add Vercel deployment setup"
git push -u origin vercel-deploy
```

## 5. Create the Vercel project

1. vercel.com → sign up with GitHub → **Add New… → Project** → import `Laundry_Hub_Management_System`.
2. Framework Preset: **Other** (not Vite). Leave Root Directory as is. (Build command and output folder come from `vercel.json`.)
   The website CSS/JS is **pre-built and committed in `public/build`** (Vercel cannot build it: Composer's `vendor` folder is not there yet).
   Whenever you change anything in `resources/css` or `resources/js`, run `npm run build` and commit `public/build` before deploying.
3. Open **Environment Variables** and add these **before** the first deploy:

| Name | Value |
|---|---|
| `APP_KEY` | output of `php artisan key:generate --show` |
| `APP_URL` | `https://<your-project>.vercel.app` (fill in after the first deploy, then redeploy) |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | your TiDB values (`DB_PORT` = `4000`) |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` / `MAIL_PORT` | `smtp.gmail.com` / `587` |
| `MAIL_USERNAME` / `MAIL_FROM_ADDRESS` | your Gmail address |
| `MAIL_PASSWORD` | the Gmail App Password |
| `MAIL_FROM_NAME` | `SSK Laba Dami` |
| `FCM_PROJECT_ID`, `FCM_WEB_API_KEY`, `FCM_WEB_AUTH_DOMAIN`, `FCM_WEB_SENDER_ID`, `FCM_WEB_APP_ID`, `FCM_WEB_VAPID_KEY` | same values as your local `.env` |
| `FCM_CREDENTIALS_BASE64` | base64 of the service-account JSON (below) |
| `SMS_DRIVER` | `log` for now (see "Not working yet") |

Base64 of the Firebase key (PowerShell; copy the output):

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\your-firebase-key.json"))
```

Already set for you in `vercel.json`: `APP_ENV=production`, `APP_DEBUG=false`, secure cookies,
database sessions/cache, logging to Vercel's log stream, `/tmp` cache paths and the TLS certificate path.

4. Click **Deploy**. If the production branch is `main`, either merge `vercel-deploy` into `main` first, or in
   *Settings → Git → Production Branch* choose `vercel-deploy`.

## 6. Test it

1. Open `https://<your-project>.vercel.app/login` and sign in as admin.
2. Staff: add a user in **Manage Users**, sign in as them, record an order, check the receipt and the QR code.
3. Mark an order ready → the customer email arrives.
4. Open the QR link on a phone → **Notify me** → mark ready → push arrives.

If you see an error page, open **Vercel → your project → Logs** (or the deployment's **Runtime Logs**). Usual causes:
missing `APP_KEY`, wrong `DB_*`, or the TLS certificate path (set `MYSQL_ATTR_SSL_CA` in the dashboard to
`/etc/ssl/certs/ca-certificates.crt` if the default does not work).

## Not working yet / limits

- **SMS** uses a spare Android phone as the gateway. Vercel cannot reach a phone on your Wi-Fi, so use the app's **Cloud Server**:
  install *SMS Gateway for Android*, switch on **Cloud Server** (tap Offline until it says Online), copy the username and password it shows,
  then in Vercel set `SMS_DRIVER=android`, `SMS_ANDROID_URL=https://api.sms-gate.app/3rdparty/v1/messages`,
  `SMS_ANDROID_USER`, `SMS_ANDROID_PASSWORD`, and redeploy. The phone must stay on, with signal and the app running.
- **Audit log** goes to Vercel's logs (kept for a limited time), not to a file.
- **First request after idle** is slow (serverless cold start).
- Vercel's free **Hobby plan is for non-commercial use**. Check their terms before running a real business on it.

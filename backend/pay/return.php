<?php
/*
 * Paystack sends the user here after checkout (callback_url), with ?reference=…
 * This page only hands the user back to the Enro app (enro://payment). It does NOT mark anything as paid:
 * the app then asks the server to verify with Paystack (the cron job re-checks too).
 */
$ref = preg_replace('/[^A-Za-z0-9\-_.]/', '', (string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$deep = 'enro://payment' . ($ref !== '' ? '?reference=' . rawurlencode($ref) : '');
header('Cache-Control: no-store');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment received · Enro</title>
<style>
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #F5F4FA; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #16141F; }
  .card { background: #fff; border-radius: 22px; padding: 32px 26px; max-width: 360px; margin: 20px; text-align: center; box-shadow: 0 20px 50px -25px rgba(40,20,120,.35); }
  .ic { width: 64px; height: 64px; border-radius: 50%; background: #DCF6E9; color: #0E9F62; display: grid; place-items: center; font-size: 34px; margin: 0 auto 14px; }
  h1 { font-size: 21px; margin: 0 0 8px; } p { color: #6D6A80; line-height: 1.5; margin: 0 0 22px; font-size: 15px; }
  a { display: block; background: #5B3DF5; color: #fff; text-decoration: none; font-weight: 700; padding: 15px; border-radius: 14px; }
</style></head>
<body><div class="card">
  <div class="ic">✓</div>
  <h1>Payment complete</h1>
  <p>Head back to Enro. We'll confirm your payment and unlock special tasks right away.</p>
  <a href="<?= htmlspecialchars($deep, ENT_QUOTES) ?>">Return to Enro</a>
</div>
<script>setTimeout(function () { location.href = <?= json_encode($deep) ?>; }, 400);</script>
</body></html>

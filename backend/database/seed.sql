-- Default settings and starter content. Safe to re-run (INSERT IGNORE).
INSERT IGNORE INTO settings (k, v) VALUES
 ('app_name','Enro'),
 ('coins_per_naira','10'),
 ('airtime_amounts','100,200,500,1000'),
 ('min_withdraw','1000'),
 ('withdraw_fee','50'),
 ('daily_task_limit','10'),
 ('welcome_bonus','100'),
 ('ref_bonus_referrer','200'),
 ('ref_bonus_new','100'),
 ('ref_unlock_tasks','5'),
 ('streak_bonus','100'),
 ('one_account_per_device','1'),
 ('hold_first_withdrawal','1'),
 ('vtu_auto','0'),
 ('vtu_provider','vtpass'),
 ('payout_provider','manual'),
 ('support_email','support@enro.app'),
 ('ads_banner_enabled','1'),
 ('ads_banner_html',''),
 ('ads_interstitial_enabled','1'),
 ('ads_interstitial_freq','3'),
 ('ads_interstitial_html',''),
 ('ads_native_enabled','1'),
 ('ads_native_html',''),
 ('ads_smartlink_url',''),
 ('ads_socialbar_enabled','0'),
 ('ads_socialbar_html','');

INSERT INTO tasks (title, description, type, url, reward_coins, duration_sec, daily_cap, sort)
SELECT * FROM (SELECT
  'Daily check-in' a, 'Check in every day to keep your streak alive. A 7-day streak earns bonus coins.' b, 'daily' c, NULL d, 10 e, 3 f, 1 g, 1 h) x
WHERE NOT EXISTS (SELECT 1 FROM tasks);
INSERT INTO tasks (title, description, type, url, reward_coins, duration_sec, daily_cap, sort)
SELECT * FROM (
  SELECT 'Watch a 30-second video ad', 'Watch the sponsored content to the end. Keep the app open until the timer finishes.', 'watch', NULL, 20, 30, 3, 2 UNION ALL
  SELECT 'Visit sponsor website', 'Open the sponsor page and stay on it for at least 20 seconds.', 'visit', 'https://example.com', 15, 20, 2, 3 UNION ALL
  SELECT 'Follow @EnroApp on X', 'Follow our official account, then come back to claim your coins.', 'social', 'https://x.com', 30, 15, 1, 4 UNION ALL
  SELECT 'Join Enro Telegram channel', 'Join the channel for updates, bonus codes and new special tasks.', 'social', 'https://t.me', 25, 15, 1, 5
) y WHERE (SELECT COUNT(*) FROM tasks) = 1;

INSERT INTO special_tasks (title, description, steps, url, reward_cash, proof_type, slots, eta)
SELECT * FROM (
  SELECT 'Install & sign up on PayFlex app' a, 'Install the app and create an account.' b,
   'Tap "Open task link" and install the app from the Play Store\nOpen the app and create an account with your real details\nTake a screenshot of your dashboard\nUpload the screenshot and submit for review' c,
   'https://example.com/payflex' d, 250 e, 'screenshot' f, 500 g, '5 min' h UNION ALL
  SELECT 'Subscribe to TechNaija on YouTube', 'Subscribe and like the latest video.',
   'Open the channel and tap Subscribe\nLike the latest video\nScreenshot showing "Subscribed"\nUpload and submit',
   'https://youtube.com', 80, 'screenshot', 1000, '2 min'
) z WHERE NOT EXISTS (SELECT 1 FROM special_tasks);

INSERT INTO data_plans (network, name, validity, coins_cost, provider_code)
SELECT * FROM (
  SELECT 'mtn' a,'500MB' b,'1 day' c,1500 d,'mtn-500mb' e UNION ALL SELECT 'mtn','1GB','7 days',2800,'mtn-1gb' UNION ALL SELECT 'mtn','2GB','30 days',5200,'mtn-2gb' UNION ALL
  SELECT 'glo','1GB','7 days',2600,'glo-1gb' UNION ALL SELECT 'glo','2GB','30 days',5000,'glo-2gb' UNION ALL
  SELECT 'airtel','1GB','7 days',2800,'airtel-1gb' UNION ALL SELECT 'airtel','2GB','30 days',5200,'airtel-2gb' UNION ALL
  SELECT '9mobile','1GB','7 days',2800,'9mobile-1gb'
) p WHERE NOT EXISTS (SELECT 1 FROM data_plans);

INSERT IGNORE INTO settings (k, v) VALUES
 ('push_morning_enabled','1'),
 ('push_morning_hour','9'),
 ('push_evening_enabled','1'),
 ('push_evening_hour','19'),
 ('push_new_tasks','1');

INSERT IGNORE INTO settings (k, v) VALUES
 ('mail_driver','mail'),
 ('smtp_port','465'),
 ('smtp_secure','ssl');

-- Enro database schema (MySQL 8 / MariaDB 10.6+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(190) NOT NULL UNIQUE,
  phone           VARCHAR(20)  NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  referral_code   VARCHAR(20)  NOT NULL UNIQUE,
  referred_by     INT UNSIGNED NULL,
  referral_paid   TINYINT(1)   NOT NULL DEFAULT 0,
  coins           INT          NOT NULL DEFAULT 0,
  cash            INT          NOT NULL DEFAULT 0,          -- whole naira
  device_id       VARCHAR(100) NULL,
  phone_verified_at DATETIME   NULL,
  status          ENUM('active','banned') NOT NULL DEFAULT 'active',
  ban_reason      VARCHAR(255) NULL,
  last_seen_at    DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (referred_by), INDEX (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS otp_codes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  code_hash   VARCHAR(255) NOT NULL,
  attempts    TINYINT      NOT NULL DEFAULT 0,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Coin tasks (pay coins -> airtime/data)
CREATE TABLE IF NOT EXISTS tasks (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(150) NOT NULL,
  description   TEXT NULL,
  type          ENUM('watch','visit','social','daily') NOT NULL DEFAULT 'watch',
  url           VARCHAR(500) NULL,       -- empty for "watch" = use Adsterra smartlink
  reward_coins  INT          NOT NULL,
  duration_sec  INT          NOT NULL DEFAULT 30,
  daily_cap     INT          NOT NULL DEFAULT 1,   -- times per user per day
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  sort          INT          NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  task_id     INT UNSIGNED NOT NULL,
  started_at  DATETIME     NOT NULL,
  claimed_at  DATETIME     NULL,
  INDEX (user_id, task_id, claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Special tasks (pay real cash, proof reviewed by admin)
CREATE TABLE IF NOT EXISTS special_tasks (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  steps        TEXT NULL,             -- one step per line
  url          VARCHAR(500) NULL,
  reward_cash  INT          NOT NULL, -- naira
  proof_type   ENUM('screenshot','screenshot_text','text') NOT NULL DEFAULT 'screenshot',
  slots        INT          NOT NULL DEFAULT 100,
  taken        INT          NOT NULL DEFAULT 0,
  eta          VARCHAR(30)  NULL,
  expires_at   DATE         NULL,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS submissions (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  special_task_id  INT UNSIGNED NOT NULL,
  proof_path       VARCHAR(255) NULL,
  note             VARCHAR(500) NULL,
  status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason    VARCHAR(255) NULL,
  reviewed_by      INT UNSIGNED NULL,
  reviewed_at      DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_per_user (user_id, special_task_id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_plans (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  network        ENUM('mtn','glo','airtel','9mobile') NOT NULL,
  name           VARCHAR(50)  NOT NULL,   -- e.g. 1GB
  validity       VARCHAR(30)  NOT NULL,   -- e.g. 7 days
  coins_cost     INT          NOT NULL,
  provider_code  VARCHAR(100) NULL,       -- VTU variation code
  active         TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Redemptions (airtime/data from coins) and withdrawals (cash to bank)
CREATE TABLE IF NOT EXISTS payouts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  kind            ENUM('airtime','data','cash') NOT NULL,
  network         VARCHAR(20)  NULL,
  phone           VARCHAR(20)  NULL,
  plan_id         INT UNSIGNED NULL,
  plan_name       VARCHAR(60)  NULL,
  amount          INT          NOT NULL DEFAULT 0,  -- naira value delivered
  coins_cost      INT          NOT NULL DEFAULT 0,
  fee             INT          NOT NULL DEFAULT 0,
  bank_name       VARCHAR(100) NULL,
  bank_code       VARCHAR(20)  NULL,
  account_number  VARCHAR(20)  NULL,
  account_name    VARCHAR(150) NULL,
  status          ENUM('pending','processing','paid','failed','rejected') NOT NULL DEFAULT 'pending',
  provider_ref    VARCHAR(100) NULL,
  admin_note      VARCHAR(255) NULL,
  processed_by    INT UNSIGNED NULL,
  processed_at    DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, kind), INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ledger: every balance change
CREATE TABLE IF NOT EXISTS transactions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  currency     ENUM('coin','cash') NOT NULL,
  amount       INT          NOT NULL,   -- signed
  kind         VARCHAR(30)  NOT NULL,   -- task, special, redeem, withdraw, refund, referral, bonus, streak, adjust
  description  VARCHAR(255) NOT NULL,
  status       ENUM('success','pending','failed','rejected') NOT NULL DEFAULT 'success',
  ref_type     VARCHAR(30)  NULL,
  ref_id       INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, created_at), INDEX (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  body        VARCHAR(500) NOT NULL,
  icon        VARCHAR(30)  NOT NULL DEFAULT 'bell',
  read_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k  VARCHAR(60) PRIMARY KEY,
  v  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(190) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  last_login_at  DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  k           VARCHAR(120) PRIMARY KEY,
  hits        INT NOT NULL DEFAULT 0,
  window_end  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Push notifications (Firebase Cloud Messaging device tokens)
CREATE TABLE IF NOT EXISTS push_tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(255) NOT NULL UNIQUE,
  platform    VARCHAR(10)  NOT NULL DEFAULT 'android',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messages from admin to many users (also shown in the in-app notification list)
CREATE TABLE IF NOT EXISTS broadcasts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(150) NOT NULL,
  body          VARCHAR(500) NOT NULL,
  screen        VARCHAR(30)  NULL,       -- where a tap opens: home, tasks, special, wallet, referral, task, special_task
  ref_id        INT UNSIGNED NULL,
  audience      ENUM('all','active7','active30') NOT NULL DEFAULT 'all',
  push          TINYINT(1)   NOT NULL DEFAULT 1,
  status        ENUM('queued','sending','sent') NOT NULL DEFAULT 'queued',
  last_user_id  INT UNSIGNED NOT NULL DEFAULT 0,  -- send cursor
  sent_count    INT          NOT NULL DEFAULT 0,
  fail_count    INT          NOT NULL DEFAULT 0,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at   DATETIME     NULL,
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

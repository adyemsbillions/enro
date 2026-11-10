<?php
declare(strict_types=1);

require_once BASE_PATH . '/lib/PHPMailer/Exception.php';
require_once BASE_PATH . '/lib/PHPMailer/PHPMailer.php';
require_once BASE_PATH . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Email through PHPMailer.
 * SMTP details are set in Admin → Settings → Email. Until then the hosting's built-in mail() is used,
 * which works on most cPanel hosts but is more likely to land in spam.
 */
final class Mailer
{
    public static function usesSmtp(): bool
    {
        return setting('mail_driver') === 'smtp' && setting('smtp_host');
    }

    public static function fromAddress(): string
    {
        $from = (string)setting('mail_from', '');
        if ($from) return $from;
        $host = parse_url((string)config('app_url', ''), PHP_URL_HOST) ?: 'localhost';
        return 'no-reply@' . preg_replace('/^www\./', '', $host);
    }

    /** @return array{ok:bool, error:?string} */
    public static function send(string $to, string $subject, string $html, string $text): array
    {
        $m = new PHPMailer(true);
        try {
            $m->CharSet = PHPMailer::CHARSET_UTF8;
            $m->Timeout = 20;
            if (self::usesSmtp()) {
                $m->isSMTP();
                $m->Host = (string)setting('smtp_host');
                $m->Port = (int)setting('smtp_port', '465');
                $secure = (string)setting('smtp_secure', 'ssl');
                $m->SMTPSecure = $secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : ($secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : '');
                $m->SMTPAutoTLS = $secure !== 'none';
                if (setting('smtp_user')) {
                    $m->SMTPAuth = true;
                    $m->Username = (string)setting('smtp_user');
                    $m->Password = (string)setting('smtp_pass');
                }
            } else {
                $m->isMail();
            }
            $m->setFrom(self::fromAddress(), (string)setting('mail_from_name', setting('app_name', 'Enro')));
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body = $html;
            $m->AltBody = $text;
            $m->send();
            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            $err = $m->ErrorInfo ?: $e->getMessage();
            log_line('mail', "FAILED to $to: $err");
            return ['ok' => false, 'error' => $err];
        }
    }
}

/** Branded HTML email with a big code box. */
function code_email(string $code, string $purpose): array
{
    $app = (string)setting('app_name', 'Enro');
    $reset = $purpose === 'reset';
    $subject = $reset ? "$code is your $app password reset code" : "$code is your $app verification code";
    $lead = $reset ? 'Use this code to reset your password:' : 'Use this code to verify your email and start earning:';
    $text = ($reset ? "Your $app password reset code is $code." : "Your $app verification code is $code.")
        . " This code expires in 10 minutes. Do not share it with anyone.";
    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html><body style="margin:0;background:#F5F4FA;font-family:Arial,Helvetica,sans-serif;color:#16141F">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F4FA;padding:32px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:460px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #E8E6F0">
 <tr><td style="background:#5B3DF5;padding:22px 28px;color:#ffffff;font-size:22px;font-weight:bold">' . $e($app) . '</td></tr>
 <tr><td style="padding:28px">
  <p style="margin:0 0 6px;font-size:18px;font-weight:bold">' . ($reset ? 'Reset your password' : 'Verify your email') . '</p>
  <p style="margin:0 0 20px;color:#6D6A80;font-size:14px;line-height:21px">' . $e($lead) . '</p>
  <div style="background:#EEEBFF;border-radius:14px;padding:18px;text-align:center;font-size:34px;letter-spacing:10px;font-weight:bold;color:#4527D9">' . $e($code) . '</div>
  <p style="margin:20px 0 0;color:#6D6A80;font-size:13px;line-height:20px">This code expires in 10 minutes. Do not share it with anyone — ' . $e($app) . ' staff will never ask for it.</p>
  <p style="margin:12px 0 0;color:#9A97AC;font-size:12px;line-height:18px">If you didn\'t request this, you can ignore this email.</p>
 </td></tr>
</table></td></tr></table></body></html>';
    return [$subject, $html, $text];
}

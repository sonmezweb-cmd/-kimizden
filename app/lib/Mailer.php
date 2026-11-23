<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * E-posta kuyruğu. Gönderim isteğin yanıtından sonra yapılır; SMTP hatası
 * sipariş/ödeme kaydını etkilemez. Başarısız gönderim panelden yeniden denenir.
 */
final class Mailer
{
    public const TEMPLATES = ['paid', 'draft_ready', 'revision', 'approved', 'published', 'reset', 'admin_review', 'test', 'info_submitted'];

    public static function queueTemplate(string $to, ?string $lang, string $tpl, array $vars, ?string $dedupe = null): void
    {
        $lang = I18n::valid($lang) ? $lang : 'tr';
        $vars['site'] = Settings::get('site.name', 'İkimizden');
        $subject = t("mail.$tpl.subject", $vars, $lang);
        $body = t("mail.$tpl.body", $vars, $lang) . "\n\n— " . $vars['site'] . "\n" . url('/', $lang);
        self::queue($to, $lang, $tpl, $subject, $body, $dedupe);
    }

    public static function queueAdmins(string $tpl, array $vars, string $dedupe): void
    {
        $to = Settings::get('mail.admin_notify') ?: DB::val("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
        if ($to) self::queueTemplate((string)$to, 'tr', $tpl, $vars, $dedupe . ':admin');
    }

    public static function queue(string $to, string $lang, string $tpl, string $subject, string $body, ?string $dedupe): void
    {
        try {
            DB::insert('email_queue', [
                'dedupe_key' => $dedupe, 'to_email' => $to, 'lang' => $lang, 'template' => $tpl,
                'subject' => mb_substr($subject, 0, 250), 'body_text' => $body, 'created_at' => DB::now(),
            ]);
        } catch (\PDOException $e) {
            if (!DB::isDuplicate($e)) throw $e; // aynı olay için ikinci e-posta yok
            return;
        }
        App::onShutdown(fn() => self::processQueue(5));
    }

    public static function configured(): bool
    {
        return (string)Settings::get('smtp.host', '') !== '' && (string)Settings::get('smtp.from_email', '') !== '';
    }

    public static function processQueue(int $limit = 10, ?int $onlyId = null): array
    {
        $res = ['sent' => 0, 'failed' => 0];
        if (!self::configured()) return $res;
        $rows = $onlyId
            ? DB::all("SELECT * FROM email_queue WHERE id = ? AND status <> 'sent'", [$onlyId])
            : DB::all("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 5 ORDER BY id LIMIT " . (int)$limit);
        foreach ($rows as $r) {
            // yarış koşulu: başka istek almışsa atla
            $claimed = DB::q("UPDATE email_queue SET attempts = attempts + 1 WHERE id = ? AND status <> 'sent' AND attempts = ?", [$r['id'], $r['attempts']])->rowCount();
            if (!$claimed) continue;
            $err = self::send($r['to_email'], $r['subject'], $r['body_text']);
            if ($err === null) {
                DB::update('email_queue', ['status' => 'sent', 'sent_at' => DB::now(), 'last_error' => null], 'id = ?', [$r['id']]);
                $res['sent']++;
            } else {
                $failed = ((int)$r['attempts'] + 1) >= 5 ? 'failed' : 'pending';
                DB::update('email_queue', ['status' => $failed, 'last_error' => mb_substr($err, 0, 490)], 'id = ?', [$r['id']]);
                $res['failed']++;
            }
        }
        return $res;
    }

    /** @return string|null hata metni (sırlar temizlenmiş) */
    public static function send(string $to, string $subject, string $body): ?string
    {
        if (!class_exists(PHPMailer::class)) return 'PHPMailer yüklü değil';
        $m = new PHPMailer(true);
        try {
            $m->CharSet = 'UTF-8';
            $m->isSMTP();
            $m->Host = (string)Settings::get('smtp.host');
            $m->Port = (int)Settings::get('smtp.port', 587);
            $enc = (string)Settings::get('smtp.encryption', 'tls');
            if ($enc === 'ssl') $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            elseif ($enc === 'tls') $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            else { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; }
            $user = (string)Settings::get('smtp.user', '');
            if ($user !== '') {
                $m->SMTPAuth = true;
                $m->Username = $user;
                $m->Password = (string)Settings::secret('smtp.pass');
            }
            $m->Timeout = 12;
            $m->setFrom((string)Settings::get('smtp.from_email'), (string)Settings::get('smtp.from_name', Settings::get('site.name', 'İkimizden')));
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body = self::html($subject, $body);
            $m->AltBody = $body;
            $m->send();
            return null;
        } catch (\Throwable $e) {
            $msg = Log::scrub($m->ErrorInfo ?: $e->getMessage());
            Log::error('E-posta gönderilemedi: ' . $msg);
            return $msg;
        }
    }

    private static function html(string $subject, string $body): string
    {
        $logo = App::baseUrl() . '/assets/img/logo-360.png';
        $text = nl_paras($body);
        $text = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#7a2445">$1</a>', $text) ?? $text;
        return '<!doctype html><html><body style="margin:0;background:#f7f1ea;font-family:Arial,Helvetica,sans-serif;color:#3a1426">'
            . '<div style="max-width:560px;margin:0 auto;padding:28px 20px">'
            . '<img src="' . e($logo) . '" alt="İkimizden" width="180" style="display:block;margin:0 auto 18px">'
            . '<div style="background:#fffdf9;border:1px solid #ead9cc;border-radius:12px;padding:24px;font-size:15px;line-height:1.6">'
            . '<h1 style="font-size:19px;margin:0 0 14px;color:#4a1024">' . e($subject) . '</h1>' . $text . '</div></div></body></html>';
    }
}

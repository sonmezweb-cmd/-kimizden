<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Content;
use App\DB;
use App\Invitations;
use App\Orders;
use App\View;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;

/** Paylaşım (bağlantı, QR, WhatsApp), davetli grupları ve katılım raporu. */
final class GuestsController
{
    private function ctx(string $no): array
    {
        $o = Orders::access($no);
        return [$o, Invitations::forOrder((int)$o['id'])];
    }

    public function share(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        $live = $inv['live_version_id'] ? Invitations::version((int)$inv['live_version_id']) : null;
        View::show('panel/share', ['title' => t('share.title'), 'robots' => 'noindex', 'order' => $o, 'inv' => $inv, 'live' => $live, 'tab' => 'share',
            'publicUrl' => url_raw('/i/' . $inv['slug'])], 'panel');
    }

    public function qr(string $no, string $fmt): void
    {
        [$o, $inv] = $this->ctx($no);
        $data = url_raw('/i/' . $inv['slug']);
        if (!empty($_GET['g'])) {
            $g = DB::one('SELECT * FROM guest_groups WHERE token = ? AND invitation_id = ?', [(string)$_GET['g'], $inv['id']]);
            if (!$g) abort(404);
            $data = url_raw('/i/' . $inv['slug'] . '/g/' . $g['token']);
        }
        $svg = $fmt === 'svg';
        if (!in_array($fmt, ['svg', 'png'], true)) abort(404);
        $opt = new QROptions([
            'outputType' => $svg ? QROutputInterface::MARKUP_SVG : QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false, 'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M, 'scale' => 12, 'addQuietzone' => true,
            'svgUseFillAttributes' => true, 'drawLightModules' => false,
            'moduleValues' => $svg ? [] : [],
        ]);
        $out = (new QRCode($opt))->render($data);
        header('Content-Type: ' . ($svg ? 'image/svg+xml' : 'image/png'));
        header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="davetiye-qr.' . $fmt . '"');
        header('Cache-Control: private, max-age=300');
        echo $out;
    }

    public function guests(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        $groups = DB::all('SELECT g.*, r.attending, r.adults, r.children, r.updated_at AS answered_at FROM guest_groups g LEFT JOIN rsvps r ON r.group_id = g.id WHERE g.invitation_id = ? ORDER BY g.name', [$inv['id']]);
        View::show('panel/guests', ['title' => t('guests.title'), 'robots' => 'noindex', 'order' => $o, 'inv' => $inv, 'groups' => $groups, 'tab' => 'guests',
            'allowed' => (int)($o['snapshot']['package']['guest_groups'] ?? 1) === 1], 'panel');
    }

    public function guestsSave(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        if ((int)($o['snapshot']['package']['guest_groups'] ?? 1) !== 1) abort(403, t('guests.not_in_package'));
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $lines = preg_split('/\R/u', str_in('names', 20000)) ?: [];
            $max = max(1, min(20, (int)($_POST['max_guests'] ?? 2)));
            $n = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $m = $max;
                if (preg_match('/^(.*?)[;,\t]\s*(\d{1,2})$/u', $line, $mm)) { $line = trim($mm[1]); $m = max(1, min(20, (int)$mm[2])); }
                DB::insert('guest_groups', ['invitation_id' => $inv['id'], 'name' => mb_substr($line, 0, 180), 'token' => random_token(24) . '', 'max_guests' => $m, 'created_at' => DB::now()]);
                $n++;
                if ($n >= 500) break;
            }
            flash('success', t('guests.added', ['n' => $n]));
        } elseif ($action === 'update') {
            DB::q('UPDATE guest_groups SET name = ?, max_guests = ? WHERE id = ? AND invitation_id = ?', [mb_substr(str_in('name', 180), 0, 180), max(1, min(20, (int)($_POST['max_guests'] ?? 2))), (int)($_POST['id'] ?? 0), $inv['id']]);
            flash('success', t('common.saved'));
        } elseif ($action === 'delete') {
            DB::q('DELETE FROM guest_groups WHERE id = ? AND invitation_id = ?', [(int)($_POST['id'] ?? 0), $inv['id']]);
            flash('success', t('common.deleted'));
        }
        Audit::log('guests.' . $action, 'order', (int)$o['id']);
        redirect(url('/panel/orders/' . $no . '/guests'));
    }

    public static function stats(int $invId): array
    {
        $groups = DB::all('SELECT g.id, g.name, g.max_guests, r.attending, r.adults, r.children, r.note, r.updated_at FROM guest_groups g LEFT JOIN rsvps r ON r.group_id = g.id WHERE g.invitation_id = ? ORDER BY g.name', [$invId]);
        $public = DB::all('SELECT * FROM rsvps WHERE invitation_id = ? AND group_id IS NULL ORDER BY updated_at DESC', [$invId]);
        $s = ['invited' => count($groups), 'responded' => 0, 'coming_groups' => 0, 'not_coming' => 0, 'no_answer' => 0, 'people' => 0, 'adults' => 0, 'children' => 0,
            'public_count' => count($public), 'public_coming' => 0, 'public_people' => 0, 'public_not' => 0];
        foreach ($groups as $g) {
            if ($g['attending'] === null) { $s['no_answer']++; continue; }
            $s['responded']++;
            if ((int)$g['attending'] === 1) { $s['coming_groups']++; $s['adults'] += (int)$g['adults']; $s['children'] += (int)$g['children']; }
            else $s['not_coming']++;
        }
        $s['people'] = $s['adults'] + $s['children'];
        foreach ($public as $p) {
            if ((int)$p['attending'] === 1) { $s['public_coming']++; $s['public_people'] += (int)$p['adults'] + (int)$p['children']; }
            else $s['public_not']++;
        }
        $s['total_people'] = $s['people'] + $s['public_people'];
        return ['stats' => $s, 'groups' => $groups, 'public' => $public];
    }

    public function report(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        View::show('panel/rsvps', ['title' => t('rsvp.report'), 'robots' => 'noindex', 'order' => $o, 'inv' => $inv, 'tab' => 'rsvps'] + self::stats((int)$inv['id']), 'panel');
    }

    public function csv(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        $d = self::stats((int)$inv['id']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="katilim-' . $o['public_no'] . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel için UTF-8 BOM
        $sep = ';';
        $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; // CSV formül enjeksiyonu
        fputcsv($out, [t('csv.source'), t('csv.name'), t('csv.status'), t('csv.adults'), t('csv.children'), t('csv.max'), t('csv.note'), t('csv.updated')], $sep);
        foreach ($d['groups'] as $g) {
            $st = $g['attending'] === null ? t('rsvp.st.no_answer') : ((int)$g['attending'] ? t('rsvp.st.coming') : t('rsvp.st.not_coming'));
            fputcsv($out, array_map($safe, [t('csv.group'), $g['name'], $st, (int)$g['adults'], (int)$g['children'], (int)$g['max_guests'], (string)$g['note'], (string)$g['updated_at']]), $sep);
        }
        foreach ($d['public'] as $p) {
            fputcsv($out, array_map($safe, [t('csv.public'), $p['name'], (int)$p['attending'] ? t('rsvp.st.coming') : t('rsvp.st.not_coming'), (int)$p['adults'], (int)$p['children'], '', (string)$p['note'], (string)$p['updated_at']]), $sep);
        }
        fclose($out);
    }
}

<?php
declare(strict_types=1);

namespace App;

final class Invitations
{
    public static function newSlug(): string
    {
        do {
            $s = strtolower(substr(random_token(10), 0, 10));
        } while (DB::val('SELECT 1 FROM invitations WHERE slug = ?', [$s]));
        return $s;
    }

    public static function createForOrder(int $orderId, int $themeId, string $lang): int
    {
        $now = DB::now();
        $id = DB::insert('invitations', ['order_id' => $orderId, 'slug' => self::newSlug(), 'created_at' => $now]);
        DB::insert('invitation_drafts', [
            'invitation_id' => $id, 'theme_id' => $themeId,
            'content_json' => jenc(Content::defaults([$lang], $lang)), 'field_versions_json' => '{}',
            'lock_version' => 1, 'updated_at' => $now,
        ]);
        return $id;
    }

    public static function forOrder(int $orderId): ?array
    {
        return DB::one('SELECT * FROM invitations WHERE order_id = ?', [$orderId]);
    }

    public static function draft(int $invId): array
    {
        $d = DB::one('SELECT * FROM invitation_drafts WHERE invitation_id = ?', [$invId]);
        if (!$d) throw new \RuntimeException('Taslak bulunamadı');
        $d['content'] = Content::normalize(jdec($d['content_json']));
        $d['field_versions'] = jdec($d['field_versions_json']);
        return $d;
    }

    /**
     * Alan düzeyinde iyimser kilit. Başkası aynı alanı $base sürümünden sonra değiştirdiyse
     * o alan yazılmaz ve çakışma döner; diğer alanlar kaydedilir. Sessiz ezme yok.
     * @return array{ok:bool, lock_version:int, conflicts:array, applied:array, others:array}
     */
    public static function savePatch(int $invId, array $patch, int $base, ?int $userId, ?callable $mediaCheck = null): array
    {
        return DB::tx(function () use ($invId, $patch, $base, $userId, $mediaCheck) {
            $d = DB::one('SELECT * FROM invitation_drafts WHERE invitation_id = ? FOR UPDATE', [$invId]);
            $content = Content::normalize(jdec($d['content_json']));
            $fv = jdec($d['field_versions_json']);
            $current = (int)$d['lock_version'];
            $conflicts = [];
            $applied = [];
            $clean = [];
            foreach ($patch as $path => $value) {
                if (!Content::allowedPath($path)) continue;
                $v = Content::sanitize($path, $value);
                if ($mediaCheck && in_array($path, ['cover_photo', 'gallery'], true)) $v = $mediaCheck($path, $v);
                foreach ($fv as $q => $ver) {
                    if ((int)$ver > $base && Content::pathsOverlap($path, $q)) {
                        // aynı değeri yazıyorsa çakışma sayma
                        if (Content::getPath($content, $path) == $v) continue 2;
                        $conflicts[$path] = Content::getPath($content, $path);
                        continue 2;
                    }
                }
                if (Content::getPath($content, $path) !== $v) $clean[$path] = $v;
            }
            $newVersion = $current;
            if ($clean) {
                $newVersion = $current + 1;
                foreach ($clean as $path => $v) {
                    Content::setPath($content, $path, $v);
                    $fv[$path] = $newVersion;
                    $applied[] = $path;
                }
                // olay silindiyse kapak etkinliğini düzelt
                $ids = array_column($content['events'], 'id');
                if ($content['cover_event_id'] && !in_array($content['cover_event_id'], $ids, true)) $content['cover_event_id'] = $ids[0] ?? null;
                if (!$content['cover_event_id'] && $ids) $content['cover_event_id'] = $ids[0];
                if (!in_array($content['default_lang'], $content['languages'], true)) $content['default_lang'] = $content['languages'][0];
                DB::update('invitation_drafts', [
                    'content_json' => jenc($content), 'field_versions_json' => jenc($fv),
                    'lock_version' => $newVersion, 'updated_by' => $userId, 'updated_at' => DB::now(),
                ], 'invitation_id = ?', [$invId]);
            }
            // başkalarının bu arada değiştirdiği (bu yamada olmayan) alanlar
            $others = [];
            foreach ($fv as $q => $ver) {
                if ((int)$ver > $base && (int)$ver <= $current && !array_key_exists($q, $patch)) $others[$q] = Content::getPath($content, $q);
            }
            return ['ok' => !$conflicts, 'lock_version' => $newVersion, 'conflicts' => $conflicts, 'applied' => $applied, 'others' => $others, 'content' => $content];
        });
    }

    public static function setTheme(int $invId, int $themeId): void
    {
        DB::update('invitation_drafts', ['theme_id' => $themeId, 'updated_at' => DB::now(), 'lock_version' => DB::val('SELECT lock_version + 1 FROM invitation_drafts WHERE invitation_id = ?', [$invId])], 'invitation_id = ?', [$invId]);
    }

    public static function versions(int $invId): array
    {
        return DB::all('SELECT v.*, u.name AS creator FROM invitation_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.invitation_id = ? ORDER BY v.version_no DESC', [$invId]);
    }

    public static function version(int $id): ?array
    {
        $v = DB::one('SELECT * FROM invitation_versions WHERE id = ?', [$id]);
        if ($v) $v['content'] = Content::normalize(jdec($v['content_json']));
        return $v;
    }

    public static function latestVersion(int $invId): ?array
    {
        $id = DB::val('SELECT id FROM invitation_versions WHERE invitation_id = ? ORDER BY version_no DESC LIMIT 1', [$invId]);
        return $id ? self::version((int)$id) : null;
    }

    /** Tasarımcı: taslağın anlık kopyasını sürüm olarak çiftin onayına gönderir. */
    public static function sendVersion(array $order, int $userId, string $note): array
    {
        $inv = self::forOrder((int)$order['id']);
        $r = DB::tx(function () use ($inv, $order, $userId, $note) {
            $d = DB::one('SELECT * FROM invitation_drafts WHERE invitation_id = ? FOR UPDATE', [$inv['id']]);
            $no = (int)DB::val('SELECT COALESCE(MAX(version_no),0)+1 FROM invitation_versions WHERE invitation_id = ?', [$inv['id']]);
            DB::q("UPDATE invitation_versions SET status = 'superseded' WHERE invitation_id = ? AND status IN ('sent')", [$inv['id']]);
            $vid = DB::insert('invitation_versions', [
                'invitation_id' => $inv['id'], 'version_no' => $no, 'theme_id' => $d['theme_id'],
                'content_json' => $d['content_json'], 'draft_lock_version' => $d['lock_version'],
                'status' => 'sent', 'designer_note' => $note !== '' ? $note : null,
                'created_by' => $userId, 'created_at' => DB::now(),
            ]);
            DB::update('orders', ['design_status' => 'awaiting_approval', 'updated_at' => DB::now()], 'id = ?', [$order['id']]);
            return ['id' => $vid, 'no' => $no];
        });
        Audit::log('version.sent', 'order', (int)$order['id'], ['version' => $r['no']]);
        Mailer::queueTemplate($order['user_email'], $order['user_locale'], 'draft_ready', [
            'name' => $order['user_name'], 'order' => $order['public_no'], 'version' => $r['no'],
            'link' => url('/panel/orders/' . $order['public_no'] . '/preview', $order['user_locale']),
        ], 'draft_ready:' . $r['id']);
        return $r;
    }

    /** Çift onayı: yalnız gönderilmiş ve en güncel sürüm onaylanabilir. */
    public static function approve(array $order, int $versionId, int $userId): array
    {
        $inv = self::forOrder((int)$order['id']);
        $res = DB::tx(function () use ($order, $inv, $versionId, $userId) {
            $v = DB::one('SELECT * FROM invitation_versions WHERE id = ? AND invitation_id = ? FOR UPDATE', [$versionId, $inv['id']]);
            if (!$v) return ['ok' => false, 'error' => 'error.not_found'];
            $latest = (int)DB::val('SELECT MAX(version_no) FROM invitation_versions WHERE invitation_id = ?', [$inv['id']]);
            if ($v['status'] === 'approved') return ['ok' => true, 'already' => true];
            if ($v['status'] !== 'sent' || (int)$v['version_no'] !== $latest) return ['ok' => false, 'error' => 'approve.err.not_current'];
            DB::update('invitation_versions', ['status' => 'approved', 'approved_by' => $userId, 'approved_at' => DB::now()], 'id = ?', [$versionId]);
            DB::update('orders', ['design_status' => 'approved', 'updated_at' => DB::now()], 'id = ?', [$order['id']]);
            return ['ok' => true];
        });
        if ($res['ok'] && empty($res['already'])) {
            Audit::log('version.approved', 'order', (int)$order['id'], ['version_id' => $versionId]);
            Mailer::queueAdmins('approved', ['order' => $order['public_no']], 'approved:' . $versionId);
            if ($order['designer_id']) {
                $d = DB::one('SELECT email, locale FROM users WHERE id = ?', [$order['designer_id']]);
                if ($d) Mailer::queueTemplate($d['email'], $d['locale'], 'approved', ['order' => $order['public_no']], 'approved-d:' . $versionId);
            }
            $fresh = Orders::findById((int)$order['id']);
            $res['published'] = self::publish($fresh, $versionId, $userId)['ok'];
        }
        return $res;
    }

    public static function requestRevision(array $order, int $versionId, int $userId, string $message): array
    {
        $inv = self::forOrder((int)$order['id']);
        $res = DB::tx(function () use ($order, $inv, $versionId, $userId, $message) {
            DB::one('SELECT id FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            $v = DB::one('SELECT * FROM invitation_versions WHERE id = ? AND invitation_id = ? FOR UPDATE', [$versionId, $inv['id']]);
            if (!$v) return ['ok' => false, 'error' => 'error.not_found'];
            // aynı sürüm için tekrar gönderim: hak ikinci kez düşmez
            if (DB::val('SELECT 1 FROM revision_requests WHERE version_id = ?', [$versionId])) return ['ok' => true, 'duplicate' => true];
            if ($v['status'] !== 'sent') return ['ok' => false, 'error' => 'revision.err.not_current'];
            $used = Orders::revisionsUsed((int)$order['id']);
            if ($used >= (int)$order['revisions_allowed']) return ['ok' => false, 'error' => 'revision.err.none_left'];
            try {
                DB::insert('revision_requests', ['order_id' => $order['id'], 'version_id' => $versionId, 'message' => $message, 'created_by' => $userId, 'created_at' => DB::now()]);
            } catch (\PDOException $e) {
                if (DB::isDuplicate($e)) return ['ok' => true, 'duplicate' => true];
                throw $e;
            }
            DB::update('invitation_versions', ['status' => 'revision_requested'], 'id = ?', [$versionId]);
            DB::update('orders', ['design_status' => 'revision_requested', 'updated_at' => DB::now()], 'id = ?', [$order['id']]);
            return ['ok' => true];
        });
        if ($res['ok'] && empty($res['duplicate'])) {
            Audit::log('revision.requested', 'order', (int)$order['id'], ['version_id' => $versionId]);
            $to = $order['designer_id'] ? DB::one('SELECT email, locale FROM users WHERE id = ?', [$order['designer_id']]) : null;
            if ($to) Mailer::queueTemplate($to['email'], $to['locale'], 'revision', ['order' => $order['public_no'], 'message' => $message], 'revision:' . $versionId);
            Mailer::queueAdmins('revision', ['order' => $order['public_no'], 'message' => $message], 'revision-a:' . $versionId);
        }
        return $res;
    }

    /** Onaylı sürümü aynı kalıcı bağlantıda yayımlar. Taslak düzenlemesi canlıyı etkilemez. */
    public static function publish(array $order, int $versionId, ?int $userId, bool $force = false): array
    {
        $inv = self::forOrder((int)$order['id']);
        $v = self::version($versionId);
        if (!$v || (int)$v['invitation_id'] !== (int)$inv['id'] || $v['status'] !== 'approved') return ['ok' => false, 'error' => 'publish.err.not_approved'];
        if (!(int)$order['publish_right']) return ['ok' => false, 'error' => 'publish.err.no_right'];
        if ($order['publish_status'] === 'archived' && !$force) return ['ok' => false, 'error' => 'publish.err.archived'];
        DB::tx(function () use ($inv, $order, $versionId) {
            DB::update('invitations', ['live_version_id' => $versionId, 'published_at' => $inv['published_at'] ?? DB::now(), 'archived_at' => null], 'id = ?', [$inv['id']]);
            DB::update('orders', ['publish_status' => 'published', 'updated_at' => DB::now()], 'id = ?', [$order['id']]);
        });
        Audit::log('invitation.published', 'order', (int)$order['id'], ['version_id' => $versionId]);
        Mailer::queueTemplate($order['user_email'], $order['user_locale'], 'published', [
            'name' => $order['user_name'], 'order' => $order['public_no'],
            'link' => url_raw('/i/' . $inv['slug']),
        ], 'published:' . $versionId);
        return ['ok' => true];
    }

    public static function unpublish(array $order, bool $archive): void
    {
        $inv = self::forOrder((int)$order['id']);
        DB::tx(function () use ($inv, $order, $archive) {
            DB::update('invitations', ['archived_at' => $archive ? DB::now() : null], 'id = ?', [$inv['id']]);
            DB::update('orders', ['publish_status' => $archive ? 'archived' : 'unpublished', 'updated_at' => DB::now()], 'id = ?', [$order['id']]);
        });
        Audit::log($archive ? 'invitation.archived' : 'invitation.unpublished', 'order', (int)$order['id']);
    }

    /** Herkese açık canlı davetiye (yayında değilse null). */
    public static function live(string $slug): ?array
    {
        $inv = DB::one('SELECT i.*, o.publish_status, o.id AS oid FROM invitations i JOIN orders o ON o.id = i.order_id WHERE i.slug = ?', [$slug]);
        if (!$inv || $inv['publish_status'] !== 'published' || !$inv['live_version_id']) return null;
        $v = self::version((int)$inv['live_version_id']);
        if (!$v) return null;
        return ['inv' => $inv, 'version' => $v, 'content' => $v['content'], 'theme_id' => (int)$v['theme_id']];
    }
}

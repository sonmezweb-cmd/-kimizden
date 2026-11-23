<?php
declare(strict_types=1);

namespace App;

final class Orders
{
    public static function newPublicNo(): string
    {
        do {
            $no = 'IK' . gmdate('ym') . strtoupper(substr(random_token(8), 0, 6));
        } while (DB::val('SELECT 1 FROM orders WHERE public_no = ?', [$no]));
        return $no;
    }

    /**
     * Sepeti sunucuda yeniden hesaplar. Tutarlar tam sayı kuruş.
     * @return array{ok:bool, error?:string, snapshot?:array, total?:int, currency?:string}
     */
    public static function price(int $themeId, int $packageId, array $addonIds): array
    {
        $theme = Catalog::theme($themeId);
        $pkg = Catalog::package($packageId);
        if (!$theme || (int)$theme['is_active'] !== 1) return ['ok' => false, 'error' => 'order.err.theme'];
        if (!$pkg || (int)$pkg['is_on_sale'] !== 1) return ['ok' => false, 'error' => 'order.err.package'];
        $currency = $pkg['currency'] ?: 'TRY';
        $addons = [];
        $total = $pkg['price_minor'] === null ? null : (int)$pkg['price_minor'];
        $extraRev = 0;
        foreach (Catalog::addons() as $a) {
            if (!in_array((int)$a['id'], array_map('intval', $addonIds), true)) continue;
            if ($a['price_minor'] === null) continue;
            $addons[] = ['id' => (int)$a['id'], 'code' => $a['code'], 'name' => $a['name'], 'price_minor' => (int)$a['price_minor'], 'extra_revisions' => (int)$a['extra_revisions']];
            if ($total !== null) $total += (int)$a['price_minor'];
            $extraRev += (int)$a['extra_revisions'];
        }
        $snapshot = [
            'theme' => ['id' => (int)$theme['id'], 'slug' => $theme['slug'], 'name' => $theme['name']],
            'package' => [
                'id' => (int)$pkg['id'], 'code' => $pkg['code'], 'name' => $pkg['name'], 'scope' => $pkg['scope'],
                'revisions' => (int)$pkg['revisions'], 'delivery_days' => (int)$pkg['delivery_days'],
                'price_minor' => $pkg['price_minor'] === null ? null : (int)$pkg['price_minor'],
                'max_languages' => (int)$pkg['max_languages'], 'guest_groups' => (int)$pkg['guest_groups'],
            ],
            'addons' => $addons,
            'currency' => $currency,
            'total_minor' => $total,
            'revisions_total' => (int)$pkg['revisions'] + $extraRev,
            'priced_at' => gmdate('c'),
        ];
        return ['ok' => true, 'snapshot' => $snapshot, 'total' => $total, 'currency' => $currency, 'purchasable' => Catalog::purchasable($pkg)];
    }

    public static function create(int $userId, int $themeId, int $packageId, array $addonIds, string $lang): array
    {
        $p = self::price($themeId, $packageId, $addonIds);
        if (!$p['ok']) return $p;
        $id = DB::tx(function () use ($userId, $themeId, $packageId, $p, $lang) {
            $now = DB::now();
            $oid = DB::insert('orders', [
                'public_no' => self::newPublicNo(), 'user_id' => $userId, 'theme_id' => $themeId, 'package_id' => $packageId,
                'snapshot_json' => jenc($p['snapshot']), 'currency' => $p['currency'], 'total_minor' => (int)($p['total'] ?? 0),
                'revisions_allowed' => $p['snapshot']['revisions_total'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            Invitations::createForOrder($oid, $themeId, $lang);
            return $oid;
        });
        Audit::log('order.created', 'order', $id);
        return ['ok' => true, 'id' => $id];
    }

    /** Ödenmemiş siparişte sepet değişikliği; anlık kopya yeniden alınır. */
    public static function reprice(array $order, int $packageId, array $addonIds): array
    {
        if (!in_array($order['payment_status'], ['unpaid'], true)) return ['ok' => false, 'error' => 'order.err.locked'];
        $p = self::price((int)$order['theme_id'], $packageId, $addonIds);
        if (!$p['ok']) return $p;
        DB::update('orders', [
            'package_id' => $packageId, 'snapshot_json' => jenc($p['snapshot']), 'currency' => $p['currency'],
            'total_minor' => (int)($p['total'] ?? 0), 'revisions_allowed' => $p['snapshot']['revisions_total'], 'updated_at' => DB::now(),
        ], 'id = ?', [$order['id']]);
        return ['ok' => true];
    }

    public static function find(string $no): ?array
    {
        $o = DB::one('SELECT o.*, u.email AS user_email, u.name AS user_name, u.locale AS user_locale, u.phone AS user_phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.public_no = ?', [$no]);
        if ($o) $o['snapshot'] = jdec($o['snapshot_json']);
        return $o;
    }

    public static function findById(int $id): ?array
    {
        $no = DB::val('SELECT public_no FROM orders WHERE id = ?', [$id]);
        return $no ? self::find((string)$no) : null;
    }

    /** Erişim: müşteri kendi siparişi, tasarımcı yalnız atanmış, yönetici hepsi. Sunucuda her istekte. */
    public static function access(string $no): array
    {
        $u = Auth::require();
        $o = self::find($no);
        if (!$o) abort(404, t('error.not_found'));
        $ok = match ($u['role']) {
            'admin' => true,
            'designer' => (int)$o['designer_id'] === (int)$u['id'],
            default => (int)$o['user_id'] === (int)$u['id'],
        };
        if (!$ok) abort(404, t('error.not_found')); // var olduğunu sızdırma
        return $o;
    }

    public static function revisionsUsed(int $orderId): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM revision_requests WHERE order_id = ?', [$orderId]);
    }

    /** Müşteri panosunda "sıradaki işlem" */
    public static function nextAction(array $o, ?array $inv, ?array $latest): array
    {
        if (in_array($o['payment_status'], ['unpaid', 'awaiting'], true)) return ['key' => 'next.pay', 'url' => url('/checkout/' . $o['public_no'])];
        if ($o['design_status'] === 'info_pending') return ['key' => 'next.fill_form', 'url' => url('/panel/orders/' . $o['public_no'] . '/form/names')];
        if ($o['design_status'] === 'awaiting_approval') return ['key' => 'next.review_draft', 'url' => url('/panel/orders/' . $o['public_no'] . '/preview')];
        if ($o['design_status'] === 'in_design' || $o['design_status'] === 'revision_requested') return ['key' => 'next.wait_designer', 'url' => null];
        if ($o['publish_status'] === 'published') return ['key' => 'next.share', 'url' => url('/panel/orders/' . $o['public_no'] . '/share')];
        if ($o['design_status'] === 'approved' && !(int)$o['publish_right']) return ['key' => 'next.wait_publish_right', 'url' => null];
        return ['key' => 'next.none', 'url' => null];
    }
}

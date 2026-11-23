<?php
declare(strict_types=1);

namespace App;

final class Audit
{
    public static function log(string $action, ?string $entity = null, ?int $id = null, array $details = []): void
    {
        try {
            DB::insert('audit_log', [
                'user_id' => Auth::id(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $id,
                'details_json' => $details ? jenc($details) : null,
                'ip' => client_ip(),
                'created_at' => DB::now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Denetim kaydı yazılamadı: ' . $e->getMessage());
        }
    }
}

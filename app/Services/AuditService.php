<?php

namespace App\Services;

use App\Domain\ActorType;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\Request;

/**
 * The audit trail (BUSINESS_LOGIC §13, invariant: every mutation logs).
 *
 * The previous build called its logger in ~2 of ~20 mutations, which made the
 * trail useless. Here every service write funnels through log(). Never throw
 * from here on its own — it participates in the caller's transaction so the
 * audit row and the change commit or roll back together.
 */
class AuditService
{
    public function log(
        string $action,
        string $actorType = ActorType::SYSTEM,
        ?int $actorId = null,
        ?string $actorLabel = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?string $ip = null,
    ): TransactionLog {
        return TransactionLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_label' => $actorLabel,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $this->ipToBinary($ip ?? Request::ip()),
        ]);
    }

    public function admin(string $action, ?int $adminId, ?string $label, ?string $entityType = null, ?int $entityId = null, ?string $description = null): TransactionLog
    {
        return $this->log($action, ActorType::ADMIN, $adminId, $label, $entityType, $entityId, $description);
    }

    public function customer(string $action, ?int $customerId, ?string $label, ?string $entityType = null, ?int $entityId = null, ?string $description = null): TransactionLog
    {
        return $this->log($action, ActorType::CUSTOMER, $customerId, $label, $entityType, $entityId, $description);
    }

    public function system(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): TransactionLog
    {
        return $this->log($action, ActorType::SYSTEM, null, null, $entityType, $entityId, $description);
    }

    /** Store an IPv4/IPv6 address in the 16-byte VARBINARY column, or null. */
    private function ipToBinary(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $packed = @inet_pton($ip);

        // Unknown/::1 forms still pack; anything invalid becomes null rather
        // than corrupting the column.
        return $packed === false ? null : $packed;
    }
}

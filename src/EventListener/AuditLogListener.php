<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\Trait\TenantAwareTrait;
use App\Security\TenantContext;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Crée automatiquement une entrée AuditLog (INSERT only) pour chaque INSERT,
 * UPDATE ou DELETE sur les entités métier (celles qui portent TenantAwareTrait).
 *
 * Les entités exclues (AuditLog lui-même, SuperAdminLog, PdpWebhookLog…)
 * sont listées dans EXCLUDED_CLASSES pour éviter les boucles infinies.
 *
 * L'audit log est inséré dans le même flush() que l'entité cible,
 * garantissant l'atomicité de la piste d'audit fiable.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class AuditLogListener
{
    /** Entités exclues de l'audit automatique (évite les boucles). */
    private const EXCLUDED_CLASSES = [
        AuditLog::class,
        \App\Entity\SuperAdminLog::class,
        \App\Entity\PdpWebhookLog::class,
        \App\Entity\InvoiceStatusHistory::class,  // elle-même une trace
        \App\Entity\ProductPriceHistory::class,    // idem
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RequestStack $requestStack,
    ) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $tenant = $this->tenantContext->getTenant();
        $user = $this->tenantContext->getUser();

        if (!$tenant) {
            return;  // Contexte non initialisé (commandes, workers sans tenant)
        }

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');
        $auditLogMeta = $em->getClassMetadata(AuditLog::class);

        // ── Insertions ────────────────────────────────────────────────────
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->shouldSkip($entity)) {
                continue;
            }
            $log = $this->createLog($entity, 'create', null, $this->toArray($em, $entity), $tenant, $user, $ip, $userAgent);
            $em->persist($log);
            $uow->computeChangeSet($auditLogMeta, $log);
        }

        // ── Mises à jour ──────────────────────────────────────────────────
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->shouldSkip($entity)) {
                continue;
            }
            $changeSet = $uow->getEntityChangeSet($entity);
            $log = $this->createLog($entity, 'update', $changeSet, null, $tenant, $user, $ip, $userAgent);
            $em->persist($log);
            $uow->computeChangeSet($auditLogMeta, $log);
        }

        // ── Suppressions ──────────────────────────────────────────────────
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->shouldSkip($entity)) {
                continue;
            }
            $log = $this->createLog($entity, 'delete', $this->toArray($em, $entity), null, $tenant, $user, $ip, $userAgent);
            $em->persist($log);
            $uow->computeChangeSet($auditLogMeta, $log);
        }
    }

    private function shouldSkip(object $entity): bool
    {
        // Skip si exclue explicitement
        foreach (self::EXCLUDED_CLASSES as $excluded) {
            if ($entity instanceof $excluded) {
                return true;
            }
        }

        // Skip si l'entité n'est pas tenant-aware (pas de relation tenant)
        $traits = $this->getAllTraits($entity::class);

        return !in_array(TenantAwareTrait::class, $traits, true);
    }

    private function createLog(
        object $entity,
        string $action,
        ?array $before,
        ?array $after,
        \App\Entity\Tenant $tenant,
        ?\App\Entity\User $user,
        ?string $ip,
        ?string $userAgent,
    ): AuditLog {
        $log = new AuditLog();
        $log->setTenant($tenant);
        $log->setUser($user);
        $log->setAction($action);
        $log->setEntityType($entity::class);
        $log->setEntityId(method_exists($entity, 'getId') ? (string) $entity->getId() : null);
        $log->setPayloadBefore($before);
        $log->setPayloadAfter($after);
        $log->setIpAddress($ip);
        $log->setUserAgent($userAgent ? mb_substr($userAgent, 0, 255) : null);

        return $log;
    }

    private function toArray(EntityManagerInterface $em, object $entity): array
    {
        $meta = $em->getClassMetadata($entity::class);
        $data = [];

        foreach ($meta->getFieldNames() as $field) {
            try {
                $value = $meta->getFieldValue($entity, $field);
                // Sérialiser les types non JSON-serialisables
                $data[$field] = $value instanceof \DateTimeInterface
                    ? $value->format(\DateTimeInterface::ATOM)
                    : $value;
            } catch (\Throwable) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function getAllTraits(string $class): array
    {
        $traits = [];
        do {
            $traits = array_merge($traits, array_values(class_uses($class) ?: []));
        } while ($class = get_parent_class($class));

        return $traits;
    }
}

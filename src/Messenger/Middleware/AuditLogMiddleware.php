<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Security\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware Messenger : journalise chaque message traité dans le canal "audit".
 * N'est actif que sur les messages reçus par les workers (ReceivedStamp présent),
 * pas sur le dispatch initial (qui lui ajoute déjà une entrée AuditLog via
 * AuditLogListener Doctrine).
 */
final class AuditLogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $auditLogger,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Ne journaliser que les messages traités par les workers
        if (!$envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $messageClass = $envelope->getMessage()::class;
        $tenantId = $this->tenantContext->getTenant()?->getId();

        $this->auditLogger->info('messenger.message.handled', [
            'message' => $messageClass,
            'tenant_id' => $tenantId ? (string) $tenantId : null,
        ]);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $this->auditLogger->error('messenger.message.failed', [
                'message' => $messageClass,
                'tenant_id' => $tenantId ? (string) $tenantId : null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $envelope;
    }
}

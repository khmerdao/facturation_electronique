<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\DeliverWebhookMessage;
use App\Repository\WebhookDeliveryRepository;
use App\Entity\Enum\WebhookDeliveryStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class DeliverWebhookHandler
{
    public function __construct(
        private readonly WebhookDeliveryRepository $deliveryRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(DeliverWebhookMessage $message): void
    {
        $delivery = $this->deliveryRepository->find($message->getDeliveryId());
        if (!$delivery) return;

        $endpoint = $delivery->getEndpoint();
        $payload  = $delivery->getPayload();
        $secret   = $endpoint->getSecret();

        $body      = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret ?? '');

        try {
            $response = $this->httpClient->request('POST', $endpoint->getUrl(), [
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'X-Hub-Signature-256' => $signature,
                    'X-FacturePro-Event' => $delivery->getEventType(),
                ],
                'body'    => $body,
                'timeout' => 15,
            ]);

            $status = $response->getStatusCode();
            $ok     = $status >= 200 && $status < 300;

            $delivery->setStatus($ok ? WebhookDeliveryStatus::SUCCESS : WebhookDeliveryStatus::FAILED);
            $delivery->setHttpStatus($status);
            $delivery->setDeliveredAt(new \DateTimeImmutable());
            if ($ok) $endpoint->setLastSuccessAt(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $delivery->setStatus(WebhookDeliveryStatus::FAILED);
            $this->logger->error('webhook.delivery.failed', [
                'delivery_id' => $message->getDeliveryId(),
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->em->flush();
        }
    }
}

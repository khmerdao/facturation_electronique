<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\SendRelanceEmailMessage;
use App\Repository\InvoiceRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class SendRelanceEmailHandler
{
    private const MAX_LEVEL = 3;

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
    ) {}

    public function __invoke(SendRelanceEmailMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->getInvoiceId());
        if (!$invoice) return;

        $level   = max(1, min(self::MAX_LEVEL, $message->getLevel()));
        $contact = $invoice->getContact();
        $recipient = $contact?->getBillingEmail() ?: $contact?->getEmail();
        if (!$recipient) return;

        $template = "emails/relance_{$level}.html.twig";
        $body     = $this->twig->render($template, [
            'invoice_number' => $invoice->getNumber(),
            'total_ttc'      => number_format((float) $invoice->getTotalTtc(), 2, ',', ' '),
            'currency'       => $invoice->getCurrency(),
            'due_date'       => $invoice->getDueDate()?->format('d/m/Y') ?? '—',
            'contact_name'   => $invoice->getClientNameSnapshot() ?? $contact?->getName() ?? '',
            'sender_name'    => $invoice->getTenant()->getName(),
        ]);

        $subjects = [
            1 => 'Rappel — Facture ' . $invoice->getNumber() . ' en attente de règlement',
            2 => '2ème rappel — Facture ' . $invoice->getNumber(),
            3 => 'URGENT — Facture ' . $invoice->getNumber() . ' impayée',
        ];

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to($recipient)
                ->subject($subjects[$level])
                ->html($body)
        );
    }
}

<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\SendInvoiceEmailMessage;
use App\Repository\InvoiceRepository;
use App\Service\Archive\S3StorageService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class SendInvoiceEmailHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly MailerInterface $mailer,
        private readonly S3StorageService $s3,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
    ) {}

    public function __invoke(SendInvoiceEmailMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->getInvoiceId());
        if (!$invoice) return;

        $contact    = $invoice->getContact();
        $recipient  = $message->getRecipientEmail() ?: $contact?->getBillingEmail() ?: $contact?->getEmail();
        if (!$recipient) return;

        $tenant = $invoice->getTenant();
        $body   = $this->twig->render('emails/invoice_sent.html.twig', [
            'invoice_number' => $invoice->getNumber(),
            'total_ttc'      => number_format((float) $invoice->getTotalTtc(), 2, ',', ' '),
            'currency'       => $invoice->getCurrency(),
            'due_date'       => $invoice->getDueDate()?->format('d/m/Y'),
            'contact_name'   => $invoice->getClientNameSnapshot() ?? $contact?->getName() ?? '',
            'sender_name'    => $tenant->getName(),
            'message'        => null,
        ]);

        $email = (new Email())
            ->from($tenant->getBillingEmail() ?? $this->mailerFromAddress)
            ->to($recipient)
            ->subject('Facture ' . $invoice->getNumber() . ' — ' . $tenant->getName())
            ->html($body);

        // Joindre le PDF si disponible
        if ($invoice->getPdfS3Key()) {
            try {
                $pdfContent = $this->s3->download('invoices', $invoice->getPdfS3Key());
                $email->attach($pdfContent, $invoice->getNumber() . '.pdf', 'application/pdf');
            } catch (\Throwable) {
                // PDF non disponible — envoyer quand même
            }
        }

        $this->mailer->send($email);
    }
}

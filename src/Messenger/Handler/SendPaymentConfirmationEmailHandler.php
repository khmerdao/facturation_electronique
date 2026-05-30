<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\SendPaymentConfirmationEmailMessage;
use App\Repository\PaymentRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class SendPaymentConfirmationEmailHandler
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
    ) {}

    public function __invoke(SendPaymentConfirmationEmailMessage $message): void
    {
        $payment = $this->paymentRepository->find($message->getPaymentId());
        if (!$payment || !$payment->getInvoice()) return;

        $invoice = $payment->getInvoice();
        $contact = $invoice->getContact();
        $recipient = $contact?->getBillingEmail() ?: $contact?->getEmail();
        if (!$recipient) return;

        $remaining = bcsub($invoice->getTotalTtc(), $invoice->getAmountPaid(), 2);

        $body = $this->twig->render('emails/payment_confirmation.html.twig', [
            'invoice_number' => $invoice->getNumber(),
            'amount'         => number_format((float) $payment->getAmount(), 2, ',', ' '),
            'currency'       => $payment->getCurrency(),
            'date'           => $payment->getDate()->format('d/m/Y'),
            'remaining'      => $remaining,
            'contact_name'   => $invoice->getClientNameSnapshot() ?? $contact?->getName() ?? '',
            'sender_name'    => $invoice->getTenant()->getName(),
        ]);

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to($recipient)
                ->subject('Confirmation de paiement — Facture ' . $invoice->getNumber())
                ->html($body)
        );
    }
}

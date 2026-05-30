<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\Enum\PaymentMode;
use App\Service\Invoice\InvoiceCalculatorService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'enregistrement d'un paiement sur une facture.
 *
 * Validation contextuelle : le montant ne peut pas dépasser le restant dû.
 */
final class PaymentType extends AbstractType
{
    public function __construct(
        private readonly InvoiceCalculatorService $calculator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Invoice $invoice */
        $invoice     = $options['invoice'];
        $remainingDue = $this->calculator->getRemainingDue($invoice);

        $builder
            ->add('amount', NumberType::class, [
                'label'   => 'Montant',
                'scale'   => 2,
                'html5'   => true,
                'data'    => (float) $remainingDue,
                'attr'    => [
                    'class' => 'form-control',
                    'min'   => '0.01',
                    'max'   => $remainingDue,
                    'step'  => '0.01',
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(message: 'Le montant doit être positif.'),
                ],
            ])
            ->add('date', DateType::class, [
                'label'  => 'Date du paiement',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'data'   => new \DateTimeImmutable(),
                'attr'   => ['class' => 'form-control', 'data-controller' => 'datepicker'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\LessThanOrEqual(
                        value: 'today',
                        message: 'La date de paiement ne peut pas être dans le futur.',
                    ),
                ],
            ])
            ->add('mode', ChoiceType::class, [
                'label'   => 'Mode de paiement',
                'choices' => array_combine(
                    array_map(fn(PaymentMode $m) => $m->label(), PaymentMode::cases()),
                    PaymentMode::cases()
                ),
                'choice_label' => fn(PaymentMode $m) => $m->label(),
                'constraints' => [
                    new Assert\NotNull(message: 'Veuillez sélectionner un mode de paiement.'),
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('reference', TextType::class, [
                'label'    => 'Référence (n° virement, chèque…)',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label'    => 'Notes',
                'required' => false,
                'attr'     => ['rows' => 2, 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 500),
                ],
            ]);

        // ── Validation : montant ≤ restant dû ─────────────────────────────
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($invoice): void {
            $data = $event->getData();
            $form = $event->getForm();

            if (!is_array($data) || !isset($data['amount'])) {
                return;
            }

            $amount      = (float) ($data['amount'] ?? 0);
            $remainingDue = (float) $this->calculator->getRemainingDue($invoice);

            if ($amount <= 0) {
                $form->get('amount')->addError(
                    new FormError('Le montant doit être strictement positif.')
                );
            }

            if ($amount > $remainingDue + 0.01) { // Tolérance 1 centime
                $form->get('amount')->addError(
                    new FormError(sprintf(
                        'Le montant (%.2f €) dépasse le restant dû (%.2f €).',
                        $amount,
                        $remainingDue,
                    ))
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
            'attr'               => ['novalidate' => 'novalidate'],
        ]);

        $resolver->setRequired('invoice');
        $resolver->setAllowedTypes('invoice', Invoice::class);
    }
}

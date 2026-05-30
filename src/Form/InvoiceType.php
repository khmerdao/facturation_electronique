<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\InvoiceSequence;
use App\Entity\InvoiceTemplate;
use App\Entity\Enum\InvoiceFormat;
use App\Repository\ContactRepository;
use App\Repository\InvoiceSequenceRepository;
use App\Repository\InvoiceTemplateRepository;
use App\Security\TenantContext;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire principal de création / édition de facture.
 *
 * Utilise CollectionType pour les lignes (InvoiceLineType).
 * Compatible avec l'éditeur Vue 3 (TASK-F003) : le formulaire
 * peut être soumis avec ou sans JavaScript.
 */
final class InvoiceType extends AbstractType
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContactRepository $contactRepository,
        private readonly InvoiceSequenceRepository $sequenceRepository,
        private readonly InvoiceTemplateRepository $templateRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tenant = $this->tenantContext->requireTenant();

        $builder
            // ── Client ────────────────────────────────────────────────────
            ->add('contact', EntityType::class, [
                'class'        => Contact::class,
                'label'        => 'Client',
                'required'     => false,
                'placeholder'  => '— Sélectionner un client —',
                'choices'      => $this->contactRepository->findClients($tenant),
                'choice_label' => fn(Contact $c) => $c->getName(),
                'attr'         => ['class' => 'form-select'],
            ])

            // ── Dates ──────────────────────────────────────────────────────
            ->add('issueDate', DateType::class, [
                'label'   => 'Date d\'émission',
                'widget'  => 'single_text',
                'format'  => 'yyyy-MM-dd',
                'attr'    => ['class' => 'form-control', 'data-controller' => 'datepicker'],
                'data'    => new \DateTimeImmutable(),
                'constraints' => [
                    new Assert\NotBlank(message: 'La date d\'émission est obligatoire.'),
                ],
            ])
            ->add('dueDate', DateType::class, [
                'label'    => 'Date d\'échéance',
                'widget'   => 'single_text',
                'format'   => 'yyyy-MM-dd',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'data-controller' => 'datepicker'],
            ])

            // ── Options ───────────────────────────────────────────────────
            ->add('format', ChoiceType::class, [
                'label'   => 'Format électronique',
                'choices' => array_combine(
                    array_map(fn(InvoiceFormat $f) => $f->value, InvoiceFormat::cases()),
                    InvoiceFormat::cases()
                ),
                'choice_label' => fn(InvoiceFormat $f) => $f->value,
                'data'    => InvoiceFormat::FACTURX,
                'attr'    => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotNull(),
                ],
            ])
            ->add('currency', CurrencyType::class, [
                'label'               => 'Devise',
                'data'                => 'EUR',
                'preferred_choices'   => ['EUR', 'USD', 'GBP', 'CHF'],
                'attr'                => ['class' => 'form-select'],
            ])
            ->add('sequence', EntityType::class, [
                'class'        => InvoiceSequence::class,
                'label'        => 'Séquence de numérotation',
                'required'     => false,
                'placeholder'  => 'Séquence par défaut',
                'choices'      => $this->sequenceRepository->findByTenant($tenant),
                'choice_label' => fn(InvoiceSequence $s) => $s->getName(),
                'attr'         => ['class' => 'form-select form-select-sm'],
            ])
            ->add('template', EntityType::class, [
                'class'        => InvoiceTemplate::class,
                'label'        => 'Modèle PDF',
                'required'     => false,
                'placeholder'  => 'Modèle par défaut',
                'choices'      => $this->templateRepository->findByTenant($tenant),
                'choice_label' => fn(InvoiceTemplate $t) => $t->getName(),
                'attr'         => ['class' => 'form-select form-select-sm'],
            ])

            // ── Références ────────────────────────────────────────────────
            ->add('subject', TextType::class, [
                'label'    => 'Objet',
                'required' => false,
                'attr'     => ['placeholder' => 'Prestation de développement logiciel', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('clientReference', TextType::class, [
                'label'    => 'Référence client (bon de commande…)',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 100),
                ],
            ])

            // ── Notes ─────────────────────────────────────────────────────
            ->add('clientNotes', TextareaType::class, [
                'label'    => 'Mentions client (conditions de paiement, pénalités…)',
                'required' => false,
                'attr'     => ['rows' => 3, 'class' => 'form-control', 'placeholder' => 'En cas de retard de paiement…'],
                'constraints' => [
                    new Assert\Length(max: 2000),
                ],
            ])
            ->add('internalNotes', TextareaType::class, [
                'label'    => 'Notes internes (non visibles sur la facture)',
                'required' => false,
                'attr'     => ['rows' => 2, 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 2000),
                ],
            ])

            // ── Lignes ────────────────────────────────────────────────────
            ->add('lines', CollectionType::class, [
                'label'         => false,
                'entry_type'    => InvoiceLineType::class,
                'entry_options' => ['label' => false],
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
                'prototype'     => true,
                'prototype_name' => '__line__',
                'attr'          => ['class' => 'invoice-lines-collection'],
                'constraints'   => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'La facture doit contenir au moins une ligne.',
                    ),
                    new Assert\Valid(),
                ],
            ]);

        // ── Validation croisée : dueDate >= issueDate ──────────────────────
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $invoice = $event->getData();
            $form    = $event->getForm();

            if (!$invoice instanceof Invoice) {
                return;
            }

            $issueDate = $invoice->getIssueDate();
            $dueDate   = $invoice->getDueDate();

            if ($issueDate && $dueDate && $dueDate < $issueDate) {
                $form->get('dueDate')->addError(
                    new FormError('La date d\'échéance doit être postérieure ou égale à la date d\'émission.')
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Invoice::class,
            'translation_domain' => false,
            'attr'               => ['novalidate' => 'novalidate'],
        ]);
    }
}

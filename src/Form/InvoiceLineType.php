<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InvoiceLine;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Security\TenantContext;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Formulaire d'une ligne de facture.
 * Utilisé en CollectionType dans InvoiceType.
 */
final class InvoiceLineType extends AbstractType
{
    private const TVA_RATES = [
        '20 %'   => '20.00',
        '10 %'   => '10.00',
        '5,5 %'  => '5.50',
        '2,1 %'  => '2.10',
        '0 %'    => '0.00',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProductRepository $productRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tenant = $this->tenantContext->getTenant();

        $builder
            ->add('isComment', CheckboxType::class, [
                'label'    => 'Ligne commentaire',
                'required' => false,
                'attr'     => ['class' => 'form-check-input'],
            ])
            ->add('product', EntityType::class, [
                'class'        => Product::class,
                'label'        => 'Produit catalogue',
                'required'     => false,
                'placeholder'  => '— Sélectionner —',
                'choices'      => $tenant ? $this->productRepository->findAllActive($tenant) : [],
                'choice_label' => fn(Product $p) => $p->getLabel() . ' (' . $p->getReference() . ')',
                'attr'         => ['class' => 'form-select form-select-sm'],
            ])
            ->add('description', TextType::class, [
                'label'       => 'Description',
                'attr'        => ['placeholder' => 'Désignation de la prestation', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La description de la ligne est obligatoire.'),
                    new Assert\Length(max: 500),
                ],
            ])
            ->add('reference', TextType::class, [
                'label'    => 'Référence',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 64),
                ],
            ])
            ->add('quantity', NumberType::class, [
                'label'  => 'Quantité',
                'scale'  => 2,
                'html5'  => true,
                'attr'   => ['class' => 'form-control text-end', 'min' => '0', 'step' => '0.01'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0.'),
                    new Assert\LessThanOrEqual(value: 99999, message: 'La quantité ne peut pas dépasser 99 999.'),
                ],
            ])
            ->add('unit', TextType::class, [
                'label'  => 'Unité',
                'attr'   => ['class' => 'form-control', 'placeholder' => 'H / J / U'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 10),
                ],
            ])
            ->add('unitPrice', NumberType::class, [
                'label'  => 'Prix unitaire HT',
                'scale'  => 4,
                'html5'  => true,
                'attr'   => ['class' => 'form-control text-end', 'min' => '0', 'step' => '0.0001'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThanOrEqual(value: 0, message: 'Le prix ne peut pas être négatif.'),
                ],
            ])
            ->add('discount', NumberType::class, [
                'label'    => 'Remise (%)',
                'required' => false,
                'scale'    => 2,
                'html5'    => true,
                'attr'     => ['class' => 'form-control text-end', 'min' => '0', 'max' => '100', 'step' => '0.01'],
                'constraints' => [
                    new Assert\Range(
                        min: 0,
                        max: 100,
                        notInRangeMessage: 'La remise doit être entre {{ min }} et {{ max }} %.',
                    ),
                ],
            ])
            ->add('tvaRate', ChoiceType::class, [
                'label'   => 'TVA',
                'choices' => self::TVA_RATES,
                'attr'    => ['class' => 'form-select form-select-sm'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(choices: array_values(self::TVA_RATES)),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => InvoiceLine::class,
            'translation_domain' => false,
            // Validation contextuelle : si isComment = false, description est requise
            'constraints' => [
                new Assert\Callback(static function (InvoiceLine $line, ExecutionContextInterface $context): void {
                    if (!$line->isComment() && empty($line->getDescription())) {
                        $context->buildViolation('La description est obligatoire pour les lignes non-commentaire.')
                            ->atPath('description')
                            ->addViolation();
                    }
                }),
            ],
        ]);
    }
}

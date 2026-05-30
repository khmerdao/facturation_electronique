<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Product;
use App\Entity\Enum\ProductType as ProductTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création / modification d'un produit ou service du catalogue.
 */
final class ProductType extends AbstractType
{
    /** Taux de TVA légaux applicables en France. */
    private const TVA_RATES = [
        'Taux normal — 20 %'         => '20.00',
        'Taux intermédiaire — 10 %'  => '10.00',
        'Taux réduit — 5,5 %'        => '5.50',
        'Taux particulier — 2,1 %'   => '2.10',
        'Exonéré — 0 %'              => '0.00',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'label'       => 'Référence',
                'attr'        => ['placeholder' => 'SERV-001', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La référence est obligatoire.'),
                    new Assert\Length(max: 64, maxMessage: 'La référence ne peut pas dépasser {{ limit }} caractères.'),
                    new Assert\Regex(
                        pattern: '/^[\w\-\.]+$/u',
                        message: 'La référence ne peut contenir que des lettres, chiffres, tirets et points.',
                    ),
                ],
            ])
            ->add('label', TextType::class, [
                'label'       => 'Libellé',
                'attr'        => ['placeholder' => 'Développement web (heure)', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le libellé est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 2, 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 1000),
                ],
            ])
            ->add('type', EnumType::class, [
                'label'  => 'Type',
                'class'  => ProductTypeEnum::class,
                'choice_label' => fn(ProductTypeEnum $t) => match($t) {
                    ProductTypeEnum::SERVICE => 'Service',
                    ProductTypeEnum::PRODUCT => 'Produit',
                },
                'attr'   => ['class' => 'form-select'],
            ])
            ->add('unitPrice', NumberType::class, [
                'label'   => 'Prix unitaire HT',
                'scale'   => 4,
                'html5'   => true,
                'attr'    => ['placeholder' => '90.0000', 'class' => 'form-control', 'min' => '0', 'step' => '0.0001'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThanOrEqual(value: 0, message: 'Le prix ne peut pas être négatif.'),
                ],
            ])
            ->add('unit', TextType::class, [
                'label'       => 'Unité',
                'attr'        => ['placeholder' => 'H / J / U / KM…', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'unité est obligatoire.'),
                    new Assert\Length(max: 10),
                ],
            ])
            ->add('tvaRate', ChoiceType::class, [
                'label'   => 'Taux de TVA',
                'choices' => self::TVA_RATES,
                'attr'    => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(
                        choices: array_values(self::TVA_RATES),
                        message: 'Taux de TVA invalide.',
                    ),
                ],
            ])
            ->add('accountingCode', TextType::class, [
                'label'    => 'Code comptable',
                'required' => false,
                'attr'     => ['placeholder' => '706000', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 20),
                    new Assert\Regex(
                        pattern: '/^\d+$/',
                        message: 'Le code comptable doit être numérique.',
                    ),
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label'    => 'Notes',
                'required' => false,
                'attr'     => ['rows' => 2, 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 1000),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Product::class,
            'translation_domain' => false,
            'attr'               => ['novalidate' => 'novalidate'],
        ]);
    }
}

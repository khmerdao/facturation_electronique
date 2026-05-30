<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Embeddable\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'adresse réutilisable.
 * Inclus dans ContactType, OrganisationSettingsType.
 */
final class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('line1', TextType::class, [
                'label'      => 'Adresse',
                'required'   => false,
                'attr'       => ['placeholder' => 'Ligne 1', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('line2', TextType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ligne 2 (optionnel)', 'class' => 'form-control mt-2'],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label'    => 'Code postal',
                'required' => false,
                'attr'     => ['placeholder' => '75001', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 20),
                ],
            ])
            ->add('city', TextType::class, [
                'label'    => 'Ville',
                'required' => false,
                'attr'     => ['placeholder' => 'Paris', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('country', CountryType::class, [
                'label'    => 'Pays',
                'required' => false,
                'preferred_choices' => ['FR', 'BE', 'CH', 'LU', 'DE', 'ES', 'IT', 'GB'],
                'attr'     => ['class' => 'form-select'],
                'data'     => 'FR',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => Address::class,
            'label'             => false,
            'translation_domain' => false,
        ]);
    }
}

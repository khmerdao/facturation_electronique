<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant;
use App\Validator\Constraint\ValidIban;
use App\Validator\Constraint\ValidBic;
use App\Validator\Constraint\ValidSiret;
use App\Validator\Constraint\ValidTvaIntra;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire des paramètres de l'organisation (Settings > Organisation).
 *
 * Active ValidSiret, ValidTvaIntra, ValidIban, ValidBic de TASK-015.
 */
final class OrganisationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'Raison sociale',
                'attr'        => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La raison sociale est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('siret', TextType::class, [
                'label'    => 'SIRET',
                'required' => false,
                'attr'     => ['placeholder' => '356 000 000 00048', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 17),
                    new ValidSiret(),
                ],
            ])
            ->add('tvaIntra', TextType::class, [
                'label'    => 'N° TVA intracommunautaire',
                'required' => false,
                'attr'     => ['placeholder' => 'FR12345678901', 'class' => 'form-control'],
                'constraints' => [
                    new ValidTvaIntra(),
                ],
            ])
            ->add('billingEmail', EmailType::class, [
                'label'    => 'Email de facturation',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Email(),
                ],
            ])
            ->add('phone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 30),
                ],
            ])
            ->add('iban', TextType::class, [
                'label'    => 'IBAN',
                'required' => false,
                'attr'     => ['placeholder' => 'FR76 3000 6000 01…', 'class' => 'form-control'],
                'constraints' => [
                    new ValidIban(),
                ],
            ])
            ->add('bic', TextType::class, [
                'label'    => 'BIC / SWIFT',
                'required' => false,
                'attr'     => ['placeholder' => 'BNPAFRPP', 'class' => 'form-control'],
                'constraints' => [
                    new ValidBic(),
                ],
            ])
            ->add('website', UrlType::class, [
                'label'           => 'Site web',
                'required'        => false,
                'default_protocol' => 'https',
                'attr'            => ['class' => 'form-control'],
            ])
            ->add('legalMentions', TextType::class, [
                'label'    => 'Mentions légales',
                'required' => false,
                'attr'     => ['placeholder' => 'SAS au capital de 10 000 € — RCS Paris 356 000 000', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 500),
                ],
            ])
            ->add('address', AddressType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Tenant::class,
            'translation_domain' => false,
            'attr'               => ['novalidate' => 'novalidate'],
        ]);
    }
}

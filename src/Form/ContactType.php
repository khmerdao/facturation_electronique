<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Enum\ContactType as ContactTypeEnum;
use App\Validator\Constraint\ValidIban;
use App\Validator\Constraint\ValidSiret;
use App\Validator\Constraint\ValidTvaIntra;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création / modification d'un contact (client ou fournisseur).
 *
 * Active les validators TASK-015 :
 *  ValidSiret   → vérifie l'algorithme de Luhn sur 14 chiffres
 *  ValidTvaIntra → vérifie le format par pays (FR, DE, ES…)
 *  ValidIban    → vérifie la checksum ISO 13616
 */
final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Identité ──────────────────────────────────────────────────
            ->add('name', TextType::class, [
                'label'      => 'Nom / Raison sociale',
                'attr'       => ['placeholder' => 'ACME SAS', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('type', EnumType::class, [
                'label'      => 'Type',
                'class'      => ContactTypeEnum::class,
                'choice_label' => fn(ContactTypeEnum $t) => match($t) {
                    ContactTypeEnum::CLIENT   => 'Client',
                    ContactTypeEnum::SUPPLIER => 'Fournisseur',
                    ContactTypeEnum::BOTH     => 'Client & Fournisseur',
                },
                'attr'       => ['class' => 'form-select'],
            ])

            // ── Identification légale ─────────────────────────────────────
            ->add('siret', TextType::class, [
                'label'    => 'SIRET',
                'required' => false,
                'attr'     => [
                    'placeholder'  => '123 456 789 01234',
                    'class'        => 'form-control',
                    'maxlength'    => '17',
                    'data-controller' => 'siret-lookup',
                    'data-siret-lookup-url-value' => '/contacts/api-sirene',
                    'data-siret-lookup-name-target' => '',
                ],
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
                    new Assert\Length(max: 20),
                    new ValidTvaIntra(),
                ],
            ])

            // ── Contact ───────────────────────────────────────────────────
            ->add('email', EmailType::class, [
                'label'    => 'Email',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.'),
                    new Assert\Length(max: 180),
                ],
            ])
            ->add('billingEmail', EmailType::class, [
                'label'    => 'Email facturation',
                'required' => false,
                'attr'     => ['placeholder' => 'comptabilite@entreprise.fr', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Email(),
                    new Assert\Length(max: 180),
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
            ->add('website', UrlType::class, [
                'label'    => 'Site web',
                'required' => false,
                'attr'     => ['placeholder' => 'https://...', 'class' => 'form-control'],
                'default_protocol' => 'https',
            ])

            // ── Identifiant PDP ───────────────────────────────────────────
            ->add('pdpIdentifier', TextType::class, [
                'label'    => 'Identifiant PDP',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Identifiant destinataire PDP partenaire',
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])

            // ── Coordonnées bancaires ─────────────────────────────────────
            ->add('iban', TextType::class, [
                'label'    => 'IBAN',
                'required' => false,
                'attr'     => ['placeholder' => 'FR76 3000 6000 01...', 'class' => 'form-control'],
                'constraints' => [
                    new ValidIban(),
                ],
            ])

            // ── Adresse ───────────────────────────────────────────────────
            ->add('address', AddressType::class, [
                'label' => 'Adresse',
            ])

            // ── Notes ─────────────────────────────────────────────────────
            ->add('notes', TextareaType::class, [
                'label'    => 'Notes internes',
                'required' => false,
                'attr'     => ['rows' => 3, 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 2000),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Contact::class,
            'translation_domain' => false,
            'attr'               => ['novalidate' => 'novalidate'], // laisser Symfony valider
        ]);
    }
}

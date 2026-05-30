<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Embeddable\PdpConfig;
use App\Entity\Enum\PdpMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de configuration PDP/PPF (Settings > PDP).
 *
 * Validation contextuelle :
 *  Si mode = PDP_PARTNER → endpoint_url et emitter_id sont obligatoires.
 */
final class PdpSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', EnumType::class, [
                'label'  => 'Mode de connexion',
                'class'  => PdpMode::class,
                'choice_label' => fn(PdpMode $m) => match($m) {
                    PdpMode::PPF         => 'PPF — Portail Public de Facturation (Chorus Pro)',
                    PdpMode::PDP_PARTNER => 'PDP — Plateforme de Dématérialisation Partenaire',
                },
                'attr'   => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotNull(),
                ],
            ])
            ->add('pdpName', TextType::class, [
                'label'    => 'Nom du PDP',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : Chorus Pro, Yooz, Basware…', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('endpointUrl', UrlType::class, [
                'label'           => 'URL de l\'endpoint PDP',
                'required'        => false,
                'default_protocol' => 'https',
                'attr'            => ['placeholder' => 'https://api.monpdp.fr/v1', 'class' => 'form-control'],
                'constraints'     => [
                    new Assert\Url(message: 'L\'URL de l\'endpoint doit être une URL valide.'),
                    new Assert\Length(max: 500),
                ],
            ])
            ->add('emitterId', TextType::class, [
                'label'    => 'Identifiant émetteur',
                'required' => false,
                'attr'     => ['placeholder' => 'ID fourni par votre PDP', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('apiKey', PasswordType::class, [
                'label'    => 'Clé API PDP',
                'required' => false,
                'mapped'   => false, // Géré manuellement dans le controller (chiffrement)
                'always_empty' => true,
                'attr'     => [
                    'placeholder'  => 'Laisser vide pour conserver la clé existante',
                    'class'        => 'form-control',
                    'autocomplete' => 'new-password',
                ],
            ]);

        // ── Validation contextuelle : PDP_PARTNER → endpoint + emitterId requis ──
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $config = $event->getData();
            $form   = $event->getForm();

            if (!$config instanceof PdpConfig) {
                return;
            }

            if ($config->getMode() === PdpMode::PDP_PARTNER) {
                if (empty($config->getEndpointUrl())) {
                    $form->get('endpointUrl')->addError(
                        new FormError('L\'URL de l\'endpoint est obligatoire pour un PDP partenaire.')
                    );
                }
                if (empty($config->getEmitterId())) {
                    $form->get('emitterId')->addError(
                        new FormError('L\'identifiant émetteur est obligatoire pour un PDP partenaire.')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => PdpConfig::class,
            'translation_domain' => false,
            'attr'               => ['novalidate' => 'novalidate'],
        ]);
    }
}

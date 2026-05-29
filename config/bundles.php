<?php

return [
    // ─── Symfony Core ───────────────────────────────────────────────────────
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],

    // ─── Doctrine ───────────────────────────────────────────────────────────
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],

    // ─── Forms, Validator, Serializer ───────────────────────────────────────
    Symfony\Bundle\ValidatorBundle\ValidatorBundle::class => ['all' => true],

    // ─── Mailer & Messenger ─────────────────────────────────────────────────
    Symfony\Bundle\MailerBundle\MailerBundle::class => ['all' => true],

    // ─── Twig extras ────────────────────────────────────────────────────────
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],

    // ─── API / Auth ─────────────────────────────────────────────────────────
    Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
    Nelmio\CorsBundle\NelmioCorsBundle::class => ['all' => true],

    // ─── 2FA ────────────────────────────────────────────────────────────────
    Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],

    // ─── Redis ──────────────────────────────────────────────────────────────
    Snc\RedisBundle\SncRedisBundle::class => ['all' => true],

    // ─── Upload ─────────────────────────────────────────────────────────────
    Vich\UploaderBundle\VichUploaderBundle::class => ['all' => true],

    // ─── Webpack Encore / UX ────────────────────────────────────────────────
    Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    Symfony\UX\TurboBundle\TurboBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\LiveComponent\LiveComponentBundle::class => ['all' => true],

    // ─── Dev / Test uniquement ──────────────────────────────────────────────
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
];

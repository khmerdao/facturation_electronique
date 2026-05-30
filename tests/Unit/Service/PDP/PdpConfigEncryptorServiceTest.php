<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\PDP;

use App\Service\PDP\PdpConfigEncryptorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de PdpConfigEncryptorService.
 *
 * Couvre :
 *  - encrypt() + decrypt() : round-trip
 *  - Chaque chiffrement produit un ciphertext différent (nonce aléatoire)
 *  - decrypt() lève RuntimeException sur données corrompues
 *  - isEncrypted() détecte correctement les valeurs chiffrées vs en clair
 */
final class PdpConfigEncryptorServiceTest extends TestCase
{
    private PdpConfigEncryptorService $service;

    /** Clé de test 32 octets en base64 (doit correspondre à ENCRYPTION_KEY dans .env.test) */
    private const TEST_KEY = 'dGVzdEVuY3J5cHRpb25LZXkzMmNoYXJzTWluaW11bQ==';

    protected function setUp(): void
    {
        $this->service = new PdpConfigEncryptorService(self::TEST_KEY);
    }

    // ── encrypt / decrypt round-trip ──────────────────────────────────────

    #[Test]
    public function encrypt_then_decrypt_returns_original_plaintext(): void
    {
        $plaintext  = 'ma-cle-api-pdp-secrete-12345';
        $ciphertext = $this->service->encrypt($plaintext);

        self::assertSame($plaintext, $this->service->decrypt($ciphertext));
    }

    #[Test]
    public function encrypt_then_decrypt_works_with_special_characters(): void
    {
        $plaintext  = 'sk_live_AaBbCc123!@#$%^&*()_+-={}[]|;:,.<>?';
        $ciphertext = $this->service->encrypt($plaintext);

        self::assertSame($plaintext, $this->service->decrypt($ciphertext));
    }

    #[Test]
    public function encrypt_then_decrypt_works_with_long_key(): void
    {
        $plaintext  = str_repeat('a', 512);
        $ciphertext = $this->service->encrypt($plaintext);

        self::assertSame($plaintext, $this->service->decrypt($ciphertext));
    }

    // ── Nonce aléatoire ───────────────────────────────────────────────────

    #[Test]
    public function two_encryptions_of_same_plaintext_produce_different_ciphertexts(): void
    {
        $plaintext   = 'même-clé-api';
        $ciphertext1 = $this->service->encrypt($plaintext);
        $ciphertext2 = $this->service->encrypt($plaintext);

        self::assertNotSame(
            $ciphertext1,
            $ciphertext2,
            'Deux chiffrements du même texte doivent produire des résultats différents (nonce aléatoire)'
        );
    }

    // ── Format du ciphertext ──────────────────────────────────────────────

    #[Test]
    public function ciphertext_is_valid_base64(): void
    {
        $ciphertext = $this->service->encrypt('test');

        $decoded = base64_decode($ciphertext, strict: true);
        self::assertNotFalse($decoded, 'Le ciphertext doit être du base64 valide (strict)');
    }

    #[Test]
    public function ciphertext_is_longer_than_plaintext(): void
    {
        $plaintext  = 'clé';
        $ciphertext = $this->service->encrypt($plaintext);

        // Base64(nonce 24 + ciphertext + tag 16) > strlen(plaintext) en base64
        self::assertGreaterThan(strlen($plaintext), strlen($ciphertext));
    }

    // ── decrypt() — erreurs ───────────────────────────────────────────────

    #[Test]
    public function decrypt_throws_on_corrupted_data(): void
    {
        $this->expectException(\RuntimeException::class);

        // Base64 valide mais contenu aléatoire trop court
        $this->service->decrypt(base64_encode('trop-court'));
    }

    #[Test]
    public function decrypt_throws_on_invalid_base64(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->decrypt('!!!pas-du-base64!!!');
    }

    #[Test]
    public function decrypt_throws_on_modified_ciphertext(): void
    {
        $ciphertext = $this->service->encrypt('données originales');
        // Modifier un caractère au milieu du ciphertext
        $modified   = substr($ciphertext, 0, 10) . 'X' . substr($ciphertext, 11);

        $this->expectException(\RuntimeException::class);

        $this->service->decrypt($modified);
    }

    // ── isEncrypted() ─────────────────────────────────────────────────────

    #[Test]
    public function is_encrypted_returns_true_for_encrypted_value(): void
    {
        $ciphertext = $this->service->encrypt('api-key-123');

        self::assertTrue($this->service->isEncrypted($ciphertext));
    }

    #[Test]
    public function is_encrypted_returns_false_for_plaintext(): void
    {
        self::assertFalse($this->service->isEncrypted('sk_live_plaintext_key'));
    }

    #[Test]
    public function is_encrypted_returns_false_for_short_base64(): void
    {
        // Base64 valide mais trop court pour contenir nonce + tag
        self::assertFalse($this->service->isEncrypted(base64_encode('short')));
    }
}

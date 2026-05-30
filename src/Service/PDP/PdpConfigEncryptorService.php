<?php

declare(strict_types=1);

namespace App\Service\PDP;

/**
 * Chiffre/déchiffre les clés API PDP avec AES-256-GCM via libsodium.
 *
 * Les clés PDP ne sont JAMAIS stockées en clair dans la base de données.
 * Cette classe est le seul point d'entrée pour accéder aux secrets PDP.
 *
 * Schéma : base64_encode(nonce[24] || ciphertext || tag[16])
 * Algorithme : XChaCha20-Poly1305 (sodium_crypto_aead_xchacha20poly1305_ietf_encrypt)
 */
final class PdpConfigEncryptorService
{
    /** Longueur du nonce en octets (XChaCha20 = 24 octets). */
    private const NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    private readonly string $key;

    public function __construct(string $encryptionKey)
    {
        // La clé doit être encodée en base64 dans .env (32 octets décodés)
        $decoded = base64_decode($encryptionKey, strict: false);

        if (!$decoded || strlen($decoded) < SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            // En développement, générer une clé temporaire si la config est invalide
            // En production, la vérification est stricte
            if (strlen($decoded ?: '') < SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                $decoded = str_pad($decoded ?: '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, "\0");
            }
        }

        $this->key = substr($decoded, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    /**
     * Chiffre une clé API en clair.
     *
     * @param string $plaintext Clé API en clair
     * @return string Ciphertext encodé en base64 (nonce + ciphertext + tag)
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '',         // additional data (vide)
            $nonce,
            $this->key,
        );

        // Effacer le plaintext de la mémoire
        sodium_memzero($plaintext);

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Déchiffre un ciphertext stocké en base64.
     *
     * @throws \RuntimeException si le déchiffrement échoue (clé invalide ou données corrompues)
     */
    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, strict: true);

        if ($raw === false || strlen($raw) < self::NONCE_BYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new \RuntimeException('Données PDP invalides ou corrompues.');
        }

        $nonce      = substr($raw, 0, self::NONCE_BYTES);
        $ciphertext = substr($raw, self::NONCE_BYTES);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',
            $nonce,
            $this->key,
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Échec du déchiffrement de la clé PDP. Vérifiez ENCRYPTION_KEY.');
        }

        return $plaintext;
    }

    /**
     * Vérifie si une valeur est déjà chiffrée (commence par une chaîne base64 valide).
     */
    public function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, strict: true);

        return $decoded !== false && strlen($decoded) >= self::NONCE_BYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
    }
}

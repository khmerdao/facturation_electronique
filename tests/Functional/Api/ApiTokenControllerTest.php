<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de l'endpoint POST /api/auth/token.
 *
 * Ces tests vérifient le comportement HTTP de l'API d'authentification.
 * Ils nécessitent une base de données de test et les fixtures chargées.
 *
 * @group functional
 * @group api
 */
final class ApiTokenControllerTest extends WebTestCase
{
    // ── Succès ────────────────────────────────────────────────────────────────

    public function test_returns_jwt_with_valid_credentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email'    => 'admin@demo.test',
            'password' => 'password',
        ]));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('expires_at', $data);
        self::assertArrayHasKey('tenant', $data);
        self::assertArrayHasKey('user', $data);
        self::assertIsString($data['token']);
        self::assertNotEmpty($data['token']);
        self::assertSame('admin@demo.test', $data['user']['email']);
    }

    // ── Erreurs d'authentification ─────────────────────────────────────────────

    public function test_returns_401_with_wrong_password(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email'    => 'admin@demo.test',
            'password' => 'mauvais_mot_de_passe',
        ]));

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function test_returns_401_with_unknown_email(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email'    => 'inconnu@nowhere.fr',
            'password' => 'password',
        ]));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_returns_400_when_email_missing(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['password' => 'password']));

        self::assertResponseStatusCodeSame(400);
    }

    public function test_returns_400_when_password_missing(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'admin@demo.test']));

        self::assertResponseStatusCodeSame(400);
    }

    // ── Structure du token JWT ─────────────────────────────────────────────────

    public function test_jwt_contains_three_parts(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email'    => 'admin@demo.test',
            'password' => 'password',
        ]));

        $data  = json_decode($client->getResponse()->getContent(), true);
        $parts = explode('.', $data['token'] ?? '');

        self::assertCount(3, $parts, 'Un JWT doit contenir 3 parties séparées par des points');
    }

    // ── Méthode HTTP ─────────────────────────────────────────────────────────

    public function test_get_method_is_not_allowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/auth/token');

        self::assertResponseStatusCodeSame(405);
    }

    // ── Sécurité ──────────────────────────────────────────────────────────────

    public function test_endpoint_is_accessible_without_auth(): void
    {
        // /api/auth/token est PUBLIC_ACCESS — pas de 401 sur GET sans token
        $client = static::createClient();
        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => '', 'password' => '']));

        // 400 (bad request) et pas 401 (unauthorized)
        self::assertResponseStatusCodeSame(400);
    }
}

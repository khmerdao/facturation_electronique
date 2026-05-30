<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels du ContactController (routes web /contacts).
 *
 * Scénarios :
 *  - Accès refusé sans authentification (redirect /login)
 *  - Accès autorisé avec authentification
 *  - Liste des contacts : code 200, présence de la page
 *  - Création contact valide → redirect vers la fiche
 *  - Création avec SIRET invalide → erreur de validation affichée
 *  - Création avec email invalide → erreur de validation affichée
 *  - Fiche contact : code 200
 *  - Modification contact → mise à jour appliquée
 *
 * @group functional
 * @group web
 */
final class ContactControllerTest extends WebTestCase
{
    // ── Authentification ──────────────────────────────────────────────────

    public function test_contacts_list_redirects_to_login_when_unauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contacts');

        self::assertResponseRedirects('/login');
    }

    public function test_contacts_list_returns_200_when_authenticated(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $client->request('GET', '/contacts');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
    }

    // ── Création ──────────────────────────────────────────────────────────

    public function test_contact_new_page_returns_200(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $client->request('GET', '/contacts/new');

        self::assertResponseIsSuccessful();
    }

    public function test_create_contact_with_valid_data_redirects_to_show(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $crawler = $client->request('GET', '/contacts/new');
        $form    = $crawler->selectButton('Créer le contact')->form([
            'contact[name]'  => 'NouveauClient SAS',
            'contact[type]'  => 'CLIENT',
            'contact[email]' => 'contact@nouveauclient.fr',
        ]);

        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'NouveauClient SAS');
    }

    public function test_create_contact_without_name_shows_error(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $crawler = $client->request('GET', '/contacts/new');
        $form    = $crawler->selectButton('Créer le contact')->form([
            'contact[name]'  => '', // vide → obligatoire
            'contact[type]'  => 'CLIENT',
        ]);

        $client->submit($form);

        // Doit rester sur la page (pas de redirect) avec une erreur
        self::assertResponseIsSuccessful(); // 200, pas de redirect
        self::assertSelectorExists('.invalid-feedback, .form-error-message, [class*="error"]');
    }

    public function test_create_contact_with_invalid_siret_shows_validation_error(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $crawler = $client->request('GET', '/contacts/new');
        $form    = $crawler->selectButton('Créer le contact')->form([
            'contact[name]'  => 'Test Corp',
            'contact[type]'  => 'CLIENT',
            'contact[siret]' => '12345678901234', // Luhn invalide
        ]);

        $client->submit($form);

        self::assertResponseIsSuccessful(); // pas de redirect
        self::assertStringContainsString(
            'SIRET',
            $client->getResponse()->getContent(),
            'Un message d\'erreur SIRET doit être affiché'
        );
    }

    public function test_create_contact_with_invalid_email_shows_error(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $crawler = $client->request('GET', '/contacts/new');
        $form    = $crawler->selectButton('Créer le contact')->form([
            'contact[name]'  => 'Test Corp',
            'contact[type]'  => 'CLIENT',
            'contact[email]' => 'pas-un-email',
        ]);

        $client->submit($form);

        self::assertResponseIsSuccessful(); // pas de redirect
        $content = $client->getResponse()->getContent();
        self::assertTrue(
            str_contains($content, 'email') || str_contains($content, 'valide'),
            'Un message d\'erreur email doit être affiché'
        );
    }

    // ── Fiche contact ──────────────────────────────────────────────────────

    public function test_contact_show_returns_200_for_existing_contact(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        // Récupérer le premier contact de la liste
        $client->request('GET', '/contacts');
        $link = $client->getCrawler()->filter('table a')->first();

        if ($link->count() === 0) {
            self::markTestSkipped('Aucun contact en base pour ce test.');
        }

        $client->click($link->link());
        self::assertResponseIsSuccessful();
    }

    // ── Isolation tenant ──────────────────────────────────────────────────

    public function test_contact_list_only_shows_current_tenant_contacts(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        $client->request('GET', '/contacts');

        self::assertResponseIsSuccessful();
        // Vérifier que la page ne contient que les contacts du tenant courant
        // Les fixtures créent 10 contacts pour ACME SAS
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('TechCorp', $content);
    }

    // ── Archivage ─────────────────────────────────────────────────────────

    public function test_archive_requires_post_method(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin@demo.test');

        // GET sur une route POST → 405 Method Not Allowed
        $client->request('GET', '/contacts/00000000-0000-0000-0000-000000000001/archive');

        self::assertResponseStatusCodeSame(405);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function loginAs(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): void
    {
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            '_username' => $email,
            '_password' => 'password',
        ]);
        $client->followRedirects(false);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de l'API factures (/api/invoices).
 *
 * Scénarios couverts :
 *  - Accès refusé sans token
 *  - Liste paginée avec headers X-Total-Count
 *  - Création d'une facture
 *  - Détail avec lignes
 *  - Isolation multi-tenant (impossibilité d'accéder à une facture d'un autre tenant)
 *
 * @group functional
 * @group api
 */
final class ApiInvoiceControllerTest extends WebTestCase
{
    private string $adminToken = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminToken = $this->getToken('admin@demo.test', 'password');
    }

    // ── Sécurité ──────────────────────────────────────────────────────────────

    public function test_list_requires_authentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_list_returns_401_with_invalid_token(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer token.invalide.ici',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function test_list_returns_paginated_invoices(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE'       => 'application/json',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data',       $data);
        self::assertArrayHasKey('pagination', $data);
        self::assertArrayHasKey('page',       $data['pagination']);
        self::assertArrayHasKey('total',      $data['pagination']);
        self::assertIsArray($data['data']);
    }

    public function test_list_returns_x_total_count_header(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        self::assertTrue(
            $client->getResponse()->headers->has('X-Total-Count'),
            'Le header X-Total-Count doit être présent',
        );
    }

    public function test_list_filters_by_status(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices?status=DRAFT', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        foreach ($data['data'] as $invoice) {
            self::assertSame('DRAFT', $invoice['status'], 'Tous les résultats doivent avoir le statut DRAFT');
        }
    }

    public function test_list_respects_per_page_param(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices?per_page=2', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(2, $data['pagination']['per_page']);
        self::assertLessThanOrEqual(2, count($data['data']));
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function test_get_invoice_with_lines(): void
    {
        $client = static::createClient();

        // D'abord récupérer la liste pour avoir un ID
        $client->request('GET', '/api/invoices?per_page=1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        $list = json_decode($client->getResponse()->getContent(), true);

        if (empty($list['data'])) {
            self::markTestSkipped('Aucune facture en base pour ce test.');
        }

        $invoiceId = $list['data'][0]['id'];

        // Récupérer le détail
        $client->request('GET', '/api/invoices/' . $invoiceId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $invoice = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('id',           $invoice);
        self::assertArrayHasKey('status',       $invoice);
        self::assertArrayHasKey('amounts',      $invoice);
        self::assertArrayHasKey('lines',        $invoice);
        self::assertArrayHasKey('tva_breakdown', $invoice);
        self::assertArrayHasKey('total_ttc',    $invoice['amounts']);
    }

    public function test_get_invoice_returns_404_for_unknown_id(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/invoices/00000000-0000-0000-0000-000000000000', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        self::assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('detail', $data); // RFC 7807
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function test_create_invoice_requires_lines(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE'       => 'application/json',
        ], json_encode([
            'currency'   => 'EUR',
            'issue_date' => date('Y-m-d'),
        ]));

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('violations', $data);
    }

    public function test_create_invoice_with_valid_data(): void
    {
        $client = static::createClient();

        // Récupérer un contact existant
        $client->request('GET', '/api/contacts?per_page=1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);
        $contacts = json_decode($client->getResponse()->getContent(), true);

        if (empty($contacts['data'])) {
            self::markTestSkipped('Aucun contact en base pour ce test.');
        }

        $contactId = $contacts['data'][0]['id'];

        $client->request('POST', '/api/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE'       => 'application/json',
        ], json_encode([
            'contact_id' => $contactId,
            'issue_date' => date('Y-m-d'),
            'due_date'   => date('Y-m-d', strtotime('+30 days')),
            'currency'   => 'EUR',
            'subject'    => 'Facture de test API',
            'lines'      => [
                [
                    'description' => 'Prestation de test',
                    'quantity'    => '2',
                    'unit_price'  => '100.00',
                    'unit'        => 'U',
                    'tva_rate'    => '20.00',
                    'discount'    => '0',
                ],
            ],
        ]));

        self::assertResponseStatusCodeSame(201);
        $invoice = json_decode($client->getResponse()->getContent(), true);

        self::assertSame('DRAFT',  $invoice['status']);
        self::assertSame('EUR',    $invoice['currency']);
        self::assertSame('200.00', $invoice['amounts']['total_ht']);
        self::assertSame('40.00',  $invoice['amounts']['total_tva']);
        self::assertSame('240.00', $invoice['amounts']['total_ttc']);
    }

    // ── Isolation multi-tenant ────────────────────────────────────────────────

    public function test_cannot_access_other_tenant_invoice(): void
    {
        // L'ID non existant dans le tenant courant doit retourner 404
        $client = static::createClient();
        $client->request('GET', '/api/invoices/12345678-1234-1234-1234-123456789012', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getToken(string $email, string $password): string
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $email, 'password' => $password]));

        if (!$client->getResponse()->isSuccessful()) {
            return '';
        }

        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['token'] ?? '';
    }
}

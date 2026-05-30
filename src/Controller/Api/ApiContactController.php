<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Contact;
use App\Entity\Enum\ContactType;
use App\Repository\ContactRepository;
use App\Security\TenantContext;
use App\Security\Voter\ContactVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST — Contacts (clients et fournisseurs).
 *
 * GET  /api/contacts      — liste paginée (filtre: type, q)
 * GET  /api/contacts/{id} — détail
 * POST /api/contacts      — créer
 * PUT  /api/contacts/{id} — mettre à jour
 */
#[Route('/api/contacts', name: 'api_contacts_')]
final class ApiContactController extends AbstractApiController
{
    public function __construct(
        TenantContext $tenantContext,
        private readonly ContactRepository $contactRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($tenantContext);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant  = $this->tenantContext->requireTenant();
        $params  = $this->getPaginationParams($request);
        $type    = $request->query->get('type');
        $q       = $request->query->get('q');

        $contacts = $q
            ? $this->contactRepository->search($tenant, $q, $params['perPage'])
            : match ($type) {
                'client'   => $this->contactRepository->findClients($tenant),
                'supplier' => $this->contactRepository->findSuppliers($tenant),
                default    => $this->contactRepository->findAllActive($tenant),
            };

        $items = array_slice($contacts, $params['offset'], $params['perPage']);

        return $this->paginated(
            array_map($this->serialize(...), $items),
            count($contacts),
            $params['page'],
            $params['perPage'],
            '/api/contacts',
        );
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(Contact $contact): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($contact)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(ContactVoter::VIEW, $contact);

        return $this->success($this->serialize($contact));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ContactVoter::CREATE);
        $tenant = $this->tenantContext->requireTenant();

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->unprocessable('Le champ name est requis.');
        }

        $contact = new Contact();
        $contact->setTenant($tenant);
        $this->hydrate($contact, $data);

        $this->em->persist($contact);
        $this->em->flush();

        return $this->created($this->serialize($contact));
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Contact $contact, Request $request): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($contact)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(ContactVoter::EDIT, $contact);

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($contact, $data);
        $this->em->flush();

        return $this->success($this->serialize($contact));
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serialize(Contact $contact): array
    {
        return [
            'id'             => (string) $contact->getId(),
            'name'           => $contact->getName(),
            'type'           => $contact->getType()->value,
            'siret'          => $contact->getSiret(),
            'tva_intra'      => $contact->getTvaIntra(),
            'email'          => $contact->getEmail(),
            'billing_email'  => $contact->getBillingEmail(),
            'phone'          => $contact->getPhone(),
            'website'        => $contact->getWebsite(),
            'pdp_identifier' => $contact->getPdpIdentifier(),
            'address'        => [
                'line1'       => $contact->getAddress()->getLine1(),
                'line2'       => $contact->getAddress()->getLine2(),
                'postal_code' => $contact->getAddress()->getPostalCode(),
                'city'        => $contact->getAddress()->getCity(),
                'country'     => $contact->getAddress()->getCountry(),
            ],
            'active'         => $contact->isActive(),
            'notes'          => $contact->getNotes(),
            'created_at'     => $contact->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function hydrate(Contact $c, array $d): void
    {
        if (isset($d['name']))           $c->setName($d['name']);
        if (isset($d['type']))           $c->setType(ContactType::from($d['type']));
        if (array_key_exists('siret', $d)) $c->setSiret($d['siret'] ?: null);
        if (array_key_exists('tva_intra', $d)) $c->setTvaIntra($d['tva_intra'] ?: null);
        if (array_key_exists('email', $d)) $c->setEmail($d['email'] ?: null);
        if (array_key_exists('billing_email', $d)) $c->setBillingEmail($d['billing_email'] ?: null);
        if (array_key_exists('phone', $d)) $c->setPhone($d['phone'] ?: null);
        if (array_key_exists('pdp_identifier', $d)) $c->setPdpIdentifier($d['pdp_identifier'] ?: null);
        if (array_key_exists('notes', $d)) $c->setNotes($d['notes'] ?: null);

        if (isset($d['address'])) {
            $a = $d['address'];
            $addr = $c->getAddress();
            if (isset($a['line1']))       $addr->setLine1($a['line1']);
            if (isset($a['line2']))       $addr->setLine2($a['line2'] ?: null);
            if (isset($a['postal_code'])) $addr->setPostalCode($a['postal_code']);
            if (isset($a['city']))        $addr->setCity($a['city']);
            if (isset($a['country']))     $addr->setCountry($a['country']);
        }
    }
}

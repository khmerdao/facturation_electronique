<?php
declare(strict_types=1);
namespace App\Controller\Contact;

use App\Entity\Contact;
use App\Entity\Enum\ContactType;
use App\Form\ContactType as ContactForm;
use App\Repository\ContactRepository;
use App\Repository\InvoiceRepository;
use App\Security\TenantContext;
use App\Security\Voter\ContactVoter;
use App\Service\PDP\SireneApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contacts', name: 'app_contacts_')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContactRepository $contactRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EntityManagerInterface $em,
        private readonly SireneApiClient $sireneClient,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $type   = $request->query->get('type', 'all');
        $q      = $request->query->get('q');
        $contacts = $q
            ? $this->contactRepository->search($tenant, $q)
            : match ($type) {
                'client'   => $this->contactRepository->findClients($tenant),
                'supplier' => $this->contactRepository->findSuppliers($tenant),
                default    => $this->contactRepository->findAllActive($tenant),
            };
        return $this->render('contacts/index.html.twig', [
            'contacts' => $contacts, 'type' => $type, 'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::CREATE);
        $tenant  = $this->tenantContext->requireTenant();
        $contact = new Contact();
        $contact->setTenant($tenant);

        $form = $this->createForm(ContactForm::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contact);
            $this->em->flush();
            $this->addFlash('success', 'Contact créé.');
            return $this->redirectToRoute('app_contacts_show', ['id' => $contact->getId()]);
        }

        return $this->render('contacts/new.html.twig', [
            'contact' => $contact,
            'form'    => $form,
        ]);
    }
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '.+'])]
    public function show(Contact $contact): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::VIEW, $contact);
        $this->assertSameTenant($contact);
        return $this->render('contacts/show.html.twig', [
            'contact'  => $contact,
            'invoices' => $this->invoiceRepository->findByContact($contact, $this->tenantContext->requireTenant()),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Contact $contact, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::EDIT, $contact);
        $this->assertSameTenant($contact);

        $form = $this->createForm(ContactForm::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Contact mis à jour.');
            return $this->redirectToRoute('app_contacts_show', ['id' => $contact->getId()]);
        }

        return $this->render('contacts/edit.html.twig', [
            'contact' => $contact,
            'form'    => $form,
        ]);
    }
    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(Contact $contact): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::DELETE, $contact);
        $this->assertSameTenant($contact);
        $contact->setActive(false);
        $contact->setArchivedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->addFlash('success', 'Contact archivé.');
        return $this->redirectToRoute('app_contacts_index');
    }

    #[Route('/api-sirene/{siret}', name: 'sirene_lookup', methods: ['GET'])]
    public function sireneLookup(string $siret): Response
    {
        $data = $this->sireneClient->findBySiret($siret);
        if (!$data) {
            return $this->json(['error' => 'SIRET introuvable'], 404);
        }
        $ul = $data['uniteLegale'] ?? [];
        return $this->json([
            'name'    => ($ul['denominationUniteLegale'] ?? '') ?: trim(($ul['prenom1UniteLegale'] ?? '') . ' ' . ($ul['nomUniteLegale'] ?? '')),
            'siret'   => $siret,
            'active'  => ($data['periodesEtablissement'][0]['etatAdministratifEtablissement'] ?? '') === 'A',
        ]);
    }

    private function hydrateContact(Contact $c, array $d): void
    {
        $c->setName($d['name'] ?? '');
        $c->setType(ContactType::from($d['type'] ?? 'CLIENT'));
        $c->setSiret(!empty($d['siret']) ? $d['siret'] : null);
        $c->setTvaIntra(!empty($d['tva_intra']) ? $d['tva_intra'] : null);
        $c->setEmail(!empty($d['email']) ? $d['email'] : null);
        $c->setBillingEmail(!empty($d['billing_email']) ? $d['billing_email'] : null);
        $c->setPhone(!empty($d['phone']) ? $d['phone'] : null);
        $c->setWebsite(!empty($d['website']) ? $d['website'] : null);
        $c->setPdpIdentifier(!empty($d['pdp_identifier']) ? $d['pdp_identifier'] : null);
        $c->setNotes(!empty($d['notes']) ? $d['notes'] : null);
        $addr = $c->getAddress();
        $addr->setLine1($d['addr_line1'] ?? null);
        $addr->setLine2($d['addr_line2'] ?? null);
        $addr->setPostalCode($d['addr_postal_code'] ?? null);
        $addr->setCity($d['addr_city'] ?? null);
        $addr->setCountry($d['addr_country'] ?? 'FR');
    }

    private function assertSameTenant(Contact $contact): void
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $contact->getTenant()->getId() !== (string) $tenant->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}

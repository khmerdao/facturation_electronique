<?php
declare(strict_types=1);
namespace App\Controller\Product;
use App\Entity\Product;
use App\Entity\Enum\ProductType;
use App\Repository\ProductRepository;
use App\Security\TenantContext;
use App\Security\Voter\ProductVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', name: 'app_products_')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant   = $this->tenantContext->requireTenant();
        $q        = $request->query->get('q');
        $products = $q
            ? $this->productRepository->search($tenant, $q)
            : $this->productRepository->findAllActive($tenant);
        return $this->render('products/index.html.twig', [
            'products' => $products, 'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ProductVoter::CREATE);
        $tenant  = $this->tenantContext->requireTenant();
        $product = new Product();
        $product->setTenant($tenant);
        if ($request->isMethod('POST')) {
            $this->hydrate($product, $request->request->all());
            $this->em->persist($product);
            $this->em->flush();
            $this->addFlash('success', 'Produit créé.');
            return $this->redirectToRoute('app_products_show', ['id' => $product->getId()]);
        }
        return $this->render('products/new.html.twig', [
            'product' => $product, 'types' => ProductType::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW, $product);
        $this->assertSameTenant($product);
        return $this->render('products/show.html.twig', ['product' => $product]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Product $product, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ProductVoter::EDIT, $product);
        $this->assertSameTenant($product);
        if ($request->isMethod('POST')) {
            $this->hydrate($product, $request->request->all());
            $this->em->flush();
            $this->addFlash('success', 'Produit mis à jour.');
            return $this->redirectToRoute('app_products_show', ['id' => $product->getId()]);
        }
        return $this->render('products/edit.html.twig', [
            'product' => $product, 'types' => ProductType::cases(),
        ]);
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(Product $product): Response
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE, $product);
        $this->assertSameTenant($product);
        $product->setActive(false);
        $product->setArchivedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->addFlash('success', 'Produit archivé.');
        return $this->redirectToRoute('app_products_index');
    }

    private function hydrate(Product $p, array $d): void
    {
        $p->setReference($d['reference'] ?? '');
        $p->setLabel($d['label'] ?? '');
        $p->setDescription(!empty($d['description']) ? $d['description'] : null);
        $p->setUnitPrice($d['unit_price'] ?? '0');
        $p->setUnit($d['unit'] ?? 'U');
        $p->setTvaRate($d['tva_rate'] ?? '20.00');
        $p->setType(ProductType::from($d['type'] ?? 'SERVICE'));
        $p->setAccountingCode(!empty($d['accounting_code']) ? $d['accounting_code'] : null);
        $p->setNotes(!empty($d['notes']) ? $d['notes'] : null);
    }

    private function assertSameTenant(Product $product): void
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $product->getTenant()->getId() !== (string) $tenant->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}

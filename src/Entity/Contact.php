<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Address;
use App\Entity\Enum\ContactType;
use App\Entity\Trait\TenantAwareTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Client, fournisseur ou les deux. Le SIRET est l'identifiant pivot pour
 * le matching PDP et la vérification Sirene. L'identifiant PDP destinataire
 * (pdpIdentifier) est distinct du SIRET et conditionne la transmission B2B.
 */
#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contacts')]
#[ORM\Index(name: 'idx_contact_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_contact_siret', columns: ['siret'])]
#[ORM\Index(name: 'idx_contact_type', columns: ['type'])]
class Contact
{
    use TenantAwareTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', enumType: ContactType::class)]
    private ContactType $type;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 14, nullable: true)]
    #[Assert\Length(exactly: 14)]
    private ?string $siret = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tvaIntra = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $legalForm = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $apeCode = null;

    /** Identifiant du destinataire auprès de sa PDP/PPF (≠ SIRET). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $pdpIdentifier = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'addr_')]
    private Address $address;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'ship_')]
    private Address $shippingAddress;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasDistinctShippingAddress = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $billingEmail = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    // Paramètres de facturation (côté client)
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $paymentTerms = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $defaultDiscount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $preferredCurrency = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $documentLocale = null;

    // Paramètres fournisseur
    #[ORM\Column(length: 34, nullable: true)]
    private ?string $supplierIban = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    // Suivi Sirene
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $sireneStatus = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sireneCheckedAt = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ContactPerson> */
    #[ORM\OneToMany(targetEntity: ContactPerson::class, mappedBy: 'contact', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $persons;

    /** @var Collection<int, ContactDocument> */
    #[ORM\OneToMany(targetEntity: ContactDocument::class, mappedBy: 'contact', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->address = new Address();
        $this->shippingAddress = new Address();
        $this->persons = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): ContactType
    {
        return $this->type;
    }

    public function setType(ContactType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): self
    {
        $this->siret = $siret;

        return $this;
    }

    public function getTvaIntra(): ?string
    {
        return $this->tvaIntra;
    }

    public function setTvaIntra(?string $tvaIntra): self
    {
        $this->tvaIntra = $tvaIntra;

        return $this;
    }

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): self
    {
        $this->legalForm = $legalForm;

        return $this;
    }

    public function getApeCode(): ?string
    {
        return $this->apeCode;
    }

    public function setApeCode(?string $apeCode): self
    {
        $this->apeCode = $apeCode;

        return $this;
    }

    public function getPdpIdentifier(): ?string
    {
        return $this->pdpIdentifier;
    }

    public function setPdpIdentifier(?string $pdpIdentifier): self
    {
        $this->pdpIdentifier = $pdpIdentifier;

        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getShippingAddress(): Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(Address $shippingAddress): self
    {
        $this->shippingAddress = $shippingAddress;

        return $this;
    }

    public function hasDistinctShippingAddress(): bool
    {
        return $this->hasDistinctShippingAddress;
    }

    public function setHasDistinctShippingAddress(bool $value): self
    {
        $this->hasDistinctShippingAddress = $value;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(?string $billingEmail): self
    {
        $this->billingEmail = $billingEmail;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getPaymentTerms(): ?int
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?int $paymentTerms): self
    {
        $this->paymentTerms = $paymentTerms;

        return $this;
    }

    public function getDefaultDiscount(): ?string
    {
        return $this->defaultDiscount;
    }

    public function setDefaultDiscount(?string $defaultDiscount): self
    {
        $this->defaultDiscount = $defaultDiscount;

        return $this;
    }

    public function getPreferredCurrency(): ?string
    {
        return $this->preferredCurrency;
    }

    public function setPreferredCurrency(?string $preferredCurrency): self
    {
        $this->preferredCurrency = $preferredCurrency;

        return $this;
    }

    public function getDocumentLocale(): ?string
    {
        return $this->documentLocale;
    }

    public function setDocumentLocale(?string $documentLocale): self
    {
        $this->documentLocale = $documentLocale;

        return $this;
    }

    public function getSupplierIban(): ?string
    {
        return $this->supplierIban;
    }

    public function setSupplierIban(?string $supplierIban): self
    {
        $this->supplierIban = $supplierIban;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getSireneStatus(): ?string
    {
        return $this->sireneStatus;
    }

    public function setSireneStatus(?string $sireneStatus): self
    {
        $this->sireneStatus = $sireneStatus;

        return $this;
    }

    public function getSireneCheckedAt(): ?\DateTimeImmutable
    {
        return $this->sireneCheckedAt;
    }

    public function setSireneCheckedAt(?\DateTimeImmutable $sireneCheckedAt): self
    {
        $this->sireneCheckedAt = $sireneCheckedAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    /** @return Collection<int, ContactPerson> */
    public function getPersons(): Collection
    {
        return $this->persons;
    }

    public function addPerson(ContactPerson $person): self
    {
        if (!$this->persons->contains($person)) {
            $this->persons->add($person);
            $person->setContact($this);
        }

        return $this;
    }

    public function removePerson(ContactPerson $person): self
    {
        if ($this->persons->removeElement($person)) {
            if ($person->getContact() === $this) {
                $person->setContact(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, ContactDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ContactDocument $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setContact($this);
        }

        return $this;
    }

    public function removeDocument(ContactDocument $document): self
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getContact() === $this) {
                $document->setContact(null);
            }
        }

        return $this;
    }

    /**
     * Adresse de facturation. Retourne l'adresse de livraison si distincte,
     * sinon l'adresse principale.
     */
    public function getBillingAddress(): \App\Entity\Embeddable\Address
    {
        return $this->getAddress();
    }
}

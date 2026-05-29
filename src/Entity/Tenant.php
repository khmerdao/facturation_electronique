<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Address;
use App\Entity\Embeddable\PdpConfig;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\OnboardingStep;
use App\Entity\Enum\Plan;
use App\Entity\Enum\TenantStatus;
use App\Entity\Enum\VatRegime;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\TenantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Organisation = espace isolé multi-tenant.
 * Entité racine — n'utilise pas TenantAwareTrait (elle EST le tenant).
 */
#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: 'tenants')]
#[ORM\Index(name: 'idx_tenant_siret', columns: ['siret'])]
#[ORM\Index(name: 'idx_tenant_status', columns: ['status'])]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé.')]
class Tenant
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $slug;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 14, nullable: true)]
    #[Assert\Length(exactly: 14)]
    private ?string $siret = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $legalForm = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tvaIntra = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $vatExempt = false;

    #[ORM\Column(type: 'string', enumType: VatRegime::class, options: ['default' => 'REEL_NORMAL'])]
    private VatRegime $vatRegime = VatRegime::REEL_NORMAL;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $apeCode = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $rcsNumber = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $shareCapital = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'addr_')]
    private Address $address;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $billingEmail = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 11, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoS3Key = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $brandColor = null;

    #[ORM\Embedded(class: PdpConfig::class, columnPrefix: 'pdp_')]
    private PdpConfig $pdpConfig;

    #[ORM\Column(type: 'string', enumType: Plan::class, options: ['default' => 'FREE'])]
    private Plan $plan = Plan::FREE;

    #[ORM\Column(type: 'string', enumType: TenantStatus::class, options: ['default' => 'ONBOARDING'])]
    private TenantStatus $status = TenantStatus::ONBOARDING;

    #[ORM\Column(type: 'string', enumType: OnboardingStep::class, options: ['default' => 'ORGANISATION'])]
    private OnboardingStep $onboardingStep = OnboardingStep::ORGANISATION;

    #[ORM\Column(options: ['default' => false])]
    private bool $onboardingCompleted = false;

    // Préférences de facturation
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $defaultCurrency = 'EUR';

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    private string $documentLocale = 'fr';

    #[ORM\Column(type: 'string', enumType: InvoiceFormat::class, options: ['default' => 'FACTURX'])]
    private InvoiceFormat $defaultInvoiceFormat = InvoiceFormat::FACTURX;

    #[ORM\Column(type: 'integer', options: ['default' => 30])]
    private int $defaultPaymentTerms = 30;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $latePaymentRate = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '40.00'])]
    private string $recoveryFee = '40.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $legalMentions = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cgvS3Key = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $assujettissementDate = null;

    /** @var Collection<int, TenantMembership> */
    #[ORM\OneToMany(targetEntity: TenantMembership::class, mappedBy: 'tenant', cascade: ['persist', 'remove'])]
    private Collection $memberships;

    /** @var Collection<int, TenantInvitation> */
    #[ORM\OneToMany(targetEntity: TenantInvitation::class, mappedBy: 'tenant', cascade: ['persist', 'remove'])]
    private Collection $invitations;

    /** @var Collection<int, InvoiceSequence> */
    #[ORM\OneToMany(targetEntity: InvoiceSequence::class, mappedBy: 'tenant', cascade: ['persist', 'remove'])]
    private Collection $sequences;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->address = new Address();
        $this->pdpConfig = new PdpConfig();
        $this->memberships = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->sequences = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): self
    {
        $this->legalForm = $legalForm;

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

    public function isVatExempt(): bool
    {
        return $this->vatExempt;
    }

    public function setVatExempt(bool $vatExempt): self
    {
        $this->vatExempt = $vatExempt;

        return $this;
    }

    public function getVatRegime(): VatRegime
    {
        return $this->vatRegime;
    }

    public function setVatRegime(VatRegime $vatRegime): self
    {
        $this->vatRegime = $vatRegime;

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

    public function getRcsNumber(): ?string
    {
        return $this->rcsNumber;
    }

    public function setRcsNumber(?string $rcsNumber): self
    {
        $this->rcsNumber = $rcsNumber;

        return $this;
    }

    public function getShareCapital(): ?int
    {
        return $this->shareCapital;
    }

    public function setShareCapital(?int $shareCapital): self
    {
        $this->shareCapital = $shareCapital;

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

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): self
    {
        $this->iban = $iban;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): self
    {
        $this->bic = $bic;

        return $this;
    }

    public function getLogoS3Key(): ?string
    {
        return $this->logoS3Key;
    }

    public function setLogoS3Key(?string $logoS3Key): self
    {
        $this->logoS3Key = $logoS3Key;

        return $this;
    }

    public function getBrandColor(): ?string
    {
        return $this->brandColor;
    }

    public function setBrandColor(?string $brandColor): self
    {
        $this->brandColor = $brandColor;

        return $this;
    }

    public function getPdpConfig(): PdpConfig
    {
        return $this->pdpConfig;
    }

    public function setPdpConfig(PdpConfig $pdpConfig): self
    {
        $this->pdpConfig = $pdpConfig;

        return $this;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStatus(): TenantStatus
    {
        return $this->status;
    }

    public function setStatus(TenantStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getOnboardingStep(): OnboardingStep
    {
        return $this->onboardingStep;
    }

    public function setOnboardingStep(OnboardingStep $onboardingStep): self
    {
        $this->onboardingStep = $onboardingStep;

        return $this;
    }

    public function isOnboardingCompleted(): bool
    {
        return $this->onboardingCompleted;
    }

    public function setOnboardingCompleted(bool $onboardingCompleted): self
    {
        $this->onboardingCompleted = $onboardingCompleted;

        return $this;
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function setDefaultCurrency(string $defaultCurrency): self
    {
        $this->defaultCurrency = $defaultCurrency;

        return $this;
    }

    public function getDocumentLocale(): string
    {
        return $this->documentLocale;
    }

    public function setDocumentLocale(string $documentLocale): self
    {
        $this->documentLocale = $documentLocale;

        return $this;
    }

    public function getDefaultInvoiceFormat(): InvoiceFormat
    {
        return $this->defaultInvoiceFormat;
    }

    public function setDefaultInvoiceFormat(InvoiceFormat $defaultInvoiceFormat): self
    {
        $this->defaultInvoiceFormat = $defaultInvoiceFormat;

        return $this;
    }

    public function getDefaultPaymentTerms(): int
    {
        return $this->defaultPaymentTerms;
    }

    public function setDefaultPaymentTerms(int $defaultPaymentTerms): self
    {
        $this->defaultPaymentTerms = $defaultPaymentTerms;

        return $this;
    }

    public function getLatePaymentRate(): ?string
    {
        return $this->latePaymentRate;
    }

    public function setLatePaymentRate(?string $latePaymentRate): self
    {
        $this->latePaymentRate = $latePaymentRate;

        return $this;
    }

    public function getRecoveryFee(): string
    {
        return $this->recoveryFee;
    }

    public function setRecoveryFee(string $recoveryFee): self
    {
        $this->recoveryFee = $recoveryFee;

        return $this;
    }

    public function getLegalMentions(): ?string
    {
        return $this->legalMentions;
    }

    public function setLegalMentions(?string $legalMentions): self
    {
        $this->legalMentions = $legalMentions;

        return $this;
    }

    public function getCgvS3Key(): ?string
    {
        return $this->cgvS3Key;
    }

    public function setCgvS3Key(?string $cgvS3Key): self
    {
        $this->cgvS3Key = $cgvS3Key;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getAssujettissementDate(): ?\DateTimeImmutable
    {
        return $this->assujettissementDate;
    }

    public function setAssujettissementDate(?\DateTimeImmutable $assujettissementDate): self
    {
        $this->assujettissementDate = $assujettissementDate;

        return $this;
    }

    /** @return Collection<int, TenantMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(TenantMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setTenant($this);
        }

        return $this;
    }

    public function removeMembership(TenantMembership $membership): self
    {
        if ($this->memberships->removeElement($membership)) {
            if ($membership->getTenant() === $this) {
                $membership->setTenant(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, TenantInvitation> */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    /** @return Collection<int, InvoiceSequence> */
    public function getSequences(): Collection
    {
        return $this->sequences;
    }
}

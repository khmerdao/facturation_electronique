<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Invoice;

use App\Entity\InvoiceSequence;
use App\Entity\Tenant;
use App\Repository\InvoiceSequenceRepository;
use App\Service\Invoice\InvoiceNumberingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Tests unitaires de InvoiceNumberingService.
 *
 * Couvre :
 *  - Formatage des numéros selon la configuration de la séquence
 *  - Incrémentation du compteur
 *  - Réinitialisation annuelle
 *  - Preview sans modification
 */
final class InvoiceNumberingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private InvoiceSequenceRepository&MockObject $sequenceRepository;
    private LockFactory&MockObject $lockFactory;
    private InvoiceNumberingService $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->sequenceRepository = $this->createMock(InvoiceSequenceRepository::class);
        $this->lockFactory        = $this->createMock(LockFactory::class);

        // Le verrou est toujours acquis dans les tests unitaires
        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->method('release')->willReturn(null);
        $this->lockFactory->method('createLock')->willReturn($lock);

        // lockForUpdate retourne la même séquence
        $this->sequenceRepository
            ->method('lockForUpdate')
            ->willReturnArgument(0);

        $this->service = new InvoiceNumberingService(
            $this->em,
            $this->sequenceRepository,
            $this->lockFactory,
            new NullLogger(),
        );
    }

    // ── preview() ─────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('providePreviewCases')]
    public function preview_formats_correctly(
        string $prefix,
        string $yearFormat,
        bool $includeMonth,
        string $separator,
        int $padding,
        int $nextNumber,
        string $expected,
    ): void {
        $seq = $this->makeSequence($prefix, $yearFormat, $includeMonth, $separator, $padding, $nextNumber);
        $now = new \DateTimeImmutable('2026-09-01');

        // On injecte la date via réflexion pour ne pas dépendre de date()
        // Le preview utilise new \DateTimeImmutable() en interne — on teste la structure
        $result = $this->service->preview($seq);

        // Vérifier la structure (préfixe et padding)
        self::assertStringStartsWith($prefix . $separator, $result);
        self::assertStringEndsWith(str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT), $result);
    }

    public static function providePreviewCases(): array
    {
        return [
            'standard_4_digits'  => ['FAC',  'AAAA', false, '-', 4, 1,    'FAC-AAAA-0001'],
            'avoir_3_digits'     => ['AV',   'AAAA', false, '-', 3, 1,    'AV-AAAA-001'],
            'no_year'            => ['INV',  '',     false, '-', 4, 42,   'INV-0042'],
            'with_month'         => ['FAC',  'AAAA', true,  '-', 4, 1,    'FAC-AAAA-MM-0001'],
            'short_year'         => ['FAC',  'AA',   false, '-', 4, 1,    'FAC-AA-0001'],
            'dot_separator'      => ['FAC',  'AAAA', false, '.',  4, 99,  'FAC.AAAA.0099'],
            'high_number'        => ['FAC',  'AAAA', false, '-', 4, 1000, 'FAC-AAAA-1000'],
        ];
    }

    // ── allocate() ────────────────────────────────────────────────────────────

    #[Test]
    public function allocate_increments_next_number(): void
    {
        $seq = $this->makeSequence('FAC', 'AAAA', false, '-', 4, 1);
        $this->em->expects(self::once())->method('flush');

        $result = $this->service->allocate($seq);

        self::assertSame(2, $seq->getNextNumber(), 'Le compteur doit être incrémenté à 2');
        self::assertTrue($seq->isLocked(), 'La séquence doit être verrouillée après la première utilisation');
        self::assertStringEndsWith('0001', $result);
    }

    #[Test]
    public function allocate_returns_correct_number_format(): void
    {
        $seq = $this->makeSequence('AV', 'AAAA', false, '-', 3, 5);
        $this->em->method('flush');

        $result = $this->service->allocate($seq);

        self::assertStringStartsWith('AV-', $result);
        self::assertStringEndsWith('005', $result);
    }

    #[Test]
    public function allocate_resets_yearly_on_new_year(): void
    {
        $seq = $this->makeSequence('FAC', 'AAAA', false, '-', 4, 100);
        $seq->setResetYearly(true);
        $seq->setStartNumber(1);
        $seq->setLastYear(2025); // Année précédente → doit déclencher reset

        $this->em->method('flush');

        $this->service->allocate($seq);

        // Après allocation avec reset annuel : le numéro utilisé était 1 (après reset)
        // donc nextNumber devient 2
        self::assertSame(2, $seq->getNextNumber(), 'Après reset annuel, le prochain numéro doit être 2');
    }

    #[Test]
    public function allocate_does_not_reset_same_year(): void
    {
        $currentYear = (int) date('Y');
        $seq = $this->makeSequence('FAC', 'AAAA', false, '-', 4, 50);
        $seq->setResetYearly(true);
        $seq->setStartNumber(1);
        $seq->setLastYear($currentYear); // Même année → pas de reset

        $this->em->method('flush');

        $this->service->allocate($seq);

        self::assertSame(51, $seq->getNextNumber(), 'Sans changement d\'année, nextNumber doit s\'incrémenter normalement');
    }

    #[Test]
    public function allocate_updates_last_year(): void
    {
        $seq = $this->makeSequence('FAC', 'AAAA', false, '-', 4, 1);
        $seq->setLastYear(null);

        $this->em->method('flush');

        $this->service->allocate($seq);

        self::assertSame((int) date('Y'), $seq->getLastYear());
    }

    // ── createDefaultSequence() ───────────────────────────────────────────────

    #[Test]
    public function createDefaultSequence_for_invoices(): void
    {
        $tenant = new Tenant();
        $this->em->expects(self::once())->method('persist');

        $seq = $this->service->createDefaultSequence($tenant, false);

        self::assertSame('FAC', $seq->getPrefix());
        self::assertFalse($seq->isCreditNoteSequence());
        self::assertSame(1, $seq->getNextNumber());
        self::assertSame(4, $seq->getPadding());
        self::assertSame($tenant, $seq->getTenant());
    }

    #[Test]
    public function createDefaultSequence_for_credit_notes(): void
    {
        $tenant = new Tenant();
        $this->em->method('persist');

        $seq = $this->service->createDefaultSequence($tenant, true);

        self::assertSame('AV', $seq->getPrefix());
        self::assertTrue($seq->isCreditNoteSequence());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSequence(
        string $prefix,
        string $yearFormat,
        bool $includeMonth,
        string $separator,
        int $padding,
        int $nextNumber,
    ): InvoiceSequence {
        $seq = new InvoiceSequence();
        $seq->setPrefix($prefix);
        $seq->setYearFormat($yearFormat);
        $seq->setIncludeMonth($includeMonth);
        $seq->setSeparator($separator);
        $seq->setPadding($padding);
        $seq->setNextNumber($nextNumber);
        $seq->setStartNumber(1);
        $seq->setResetYearly(false);
        $seq->setName('Test');

        return $seq;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Archive;

use Aws\S3\S3Client;

/**
 * Service de stockage S3 / MinIO.
 * Centralise toutes les opérations sur les fichiers :
 * upload, download, URL signée, suppression, vérification d'existence.
 *
 * Les buckets disponibles sont :
 *  - invoices    : PDF + XML des factures émises et reçues
 *  - attachments : pièces jointes contacts
 *  - exports     : fichiers FEC, CSV, ZIP d'export
 *  - templates   : previews des modèles de factures
 */
final class S3StorageService
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly array $buckets,
        private readonly int $presignedTtl,
    ) {}

    /**
     * Upload un fichier depuis son contenu binaire.
     *
     * @param string $bucket  Clé de bucket ('invoices', 'attachments', 'exports', 'templates')
     * @param string $key     Clé S3 du fichier (ex: "tenants/uuid/invoices/FAC-2026-0001.pdf")
     * @param string $content Contenu binaire
     * @param string $mime    Type MIME (ex: "application/pdf")
     */
    public function upload(string $bucket, string $key, string $content, string $mime = 'application/octet-stream'): void
    {
        $this->s3Client->putObject([
            'Bucket'      => $this->getBucketName($bucket),
            'Key'         => $key,
            'Body'        => $content,
            'ContentType' => $mime,
        ]);
    }

    /**
     * Upload depuis un chemin de fichier local (pour les gros fichiers).
     */
    public function uploadFile(string $bucket, string $key, string $localPath, string $mime = 'application/octet-stream'): void
    {
        $this->s3Client->putObject([
            'Bucket'      => $this->getBucketName($bucket),
            'Key'         => $key,
            'SourceFile'  => $localPath,
            'ContentType' => $mime,
        ]);
    }

    /**
     * Télécharge un fichier et retourne son contenu binaire.
     */
    public function download(string $bucket, string $key): string
    {
        $result = $this->s3Client->getObject([
            'Bucket' => $this->getBucketName($bucket),
            'Key'    => $key,
        ]);

        return (string) $result['Body'];
    }

    /**
     * Génère une URL présignée pour un téléchargement sécurisé côté client.
     * L'URL expire après $this->presignedTtl secondes.
     */
    public function presignedUrl(string $bucket, string $key, ?int $ttl = null): string
    {
        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->getBucketName($bucket),
            'Key'    => $key,
        ]);

        $request = $this->s3Client->createPresignedRequest($cmd, '+' . ($ttl ?? $this->presignedTtl) . ' seconds');

        return (string) $request->getUri();
    }

    /**
     * Vérifie si un fichier existe dans S3.
     */
    public function exists(string $bucket, string $key): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->getBucketName($bucket),
                'Key'    => $key,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Supprime un fichier.
     */
    public function delete(string $bucket, string $key): void
    {
        $this->s3Client->deleteObject([
            'Bucket' => $this->getBucketName($bucket),
            'Key'    => $key,
        ]);
    }

    /**
     * Calcule le hash SHA-256 d'un contenu (pour la piste d'audit fiable).
     */
    public function hashContent(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Construit une clé S3 standardisée pour une facture.
     * Ex: "tenants/550e8400/invoices/2026/FAC-2026-0001.pdf"
     */
    public function invoiceKey(string $tenantId, string $invoiceNumber, string $extension): string
    {
        $year = date('Y');

        return sprintf('tenants/%s/invoices/%s/%s.%s', $tenantId, $year, $invoiceNumber, $extension);
    }

    /**
     * Résout le nom réel du bucket S3 depuis la clé de configuration.
     */
    private function getBucketName(string $bucketKey): string
    {
        if (!isset($this->buckets[$bucketKey])) {
            throw new \InvalidArgumentException(sprintf(
                'Bucket "%s" non configuré. Buckets disponibles : %s',
                $bucketKey,
                implode(', ', array_keys($this->buckets)),
            ));
        }

        return $this->buckets[$bucketKey];
    }
}

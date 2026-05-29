<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Filtre SQL Doctrine appliqué automatiquement sur toutes les entités
 * qui portent TenantAwareTrait. Ajoute WHERE tenant_id = :current_tenant
 * à chaque requête SELECT, sans modification des repositories.
 *
 * Cycle de vie :
 *   1. TenantFilterSubscriber::onKernelRequest() résout le tenant depuis
 *      la session / le token JWT.
 *   2. Il active ce filtre et lui injecte le tenant_id.
 *   3. Toutes les requêtes ORM suivantes sont automatiquement filtrées.
 *   4. Le super-admin peut désactiver le filtre pour les vues cross-tenant.
 *
 * Activation :
 *   $em->getFilters()->enable('tenant_filter')->setParameter('tenant_id', $tenantId, 'string');
 *
 * Désactivation (super-admin) :
 *   $em->getFilters()->disable('tenant_filter');
 */
class TenantFilter extends SQLFilter
{
    /**
     * Génère la clause SQL WHERE injectée dans chaque requête filtrée.
     *
     * @param ClassMetadata $targetEntity Métadonnées de l'entité interrogée
     * @param string        $targetTableAlias Alias SQL de la table (ex: "i0_")
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Vérifier que l'entité possède bien la colonne tenant_id
        // (i.e. utilise TenantAwareTrait ou a une propriété "tenant" mappée)
        if (!$targetEntity->hasAssociation('tenant')) {
            return '';
        }

        // Récupérer le nom de la colonne tenant_id depuis les métadonnées
        $tenantAssoc = $targetEntity->getAssociationMapping('tenant');
        $joinColumn = $tenantAssoc['joinColumns'][0]['name'] ?? 'tenant_id';

        try {
            // getParameter() lève une exception si le paramètre n'est pas encore défini
            return sprintf('%s.%s = %s', $targetTableAlias, $joinColumn, $this->getParameter('tenant_id'));
        } catch (\InvalidArgumentException) {
            // Filtre activé mais tenant_id pas encore injecté (login en cours)
            return '';
        }
    }
}

<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Trait\TimestampableTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Remplit automatiquement createdAt (à la création) et updatedAt (à la mise
 * à jour) sur toutes les entités qui utilisent TimestampableTrait.
 * Évite de dupliquer ce code dans chaque entité ou service.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class DoctrineTimestampListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Vérifie que l'entité utilise TimestampableTrait via réflexion
        if (!$this->usesTimestampableTrait($entity)) {
            return;
        }

        $now = new \DateTimeImmutable();

        if (!$entity->getCreatedAt()) {
            $entity->setCreatedAt($now);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->usesTimestampableTrait($entity)) {
            return;
        }

        $entity->setUpdatedAt(new \DateTimeImmutable());
    }

    private function usesTimestampableTrait(object $entity): bool
    {
        $uses = class_uses($entity);
        if (false === $uses) {
            return false;
        }

        // Chercher le trait dans la hiérarchie complète (parents + traits)
        $allTraits = [];
        $class = $entity::class;
        do {
            $allTraits = array_merge($allTraits, array_values(class_uses($class) ?: []));
        } while ($class = get_parent_class($class));

        return in_array(TimestampableTrait::class, $allTraits, true);
    }
}

<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;

class GroupAssignmentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
    ) {}

    /**
     * Synchronizes an entity's assigned groups based on a provided list of fields.
     *
     * @param Entity $destinationEntity The entity to assign groups to.
     * @param string[] $fieldsToWatch An array of field names to check for groups.
     * @param Entity|null $sourceEntity The entity to read group names from. If null, the destinationEntity is used.
     */
    public function syncGroupsFromFields(Entity $destinationEntity, array $fieldsToWatch, ?Entity $sourceEntity = null): void
    {
        $sourceEntity = $sourceEntity ?? $destinationEntity;
        
        $this->log->info("Syncing group assignments for {$destinationEntity->getEntityType()} ID: {$destinationEntity->getId()} from {$sourceEntity->getEntityType()} ID: {$sourceEntity->getId()}");

        $requiredGroupIds = $this->getGroupsFromFields($sourceEntity, $fieldsToWatch);
        $this->setGroupsForEntity($destinationEntity, $requiredGroupIds);
    }

    private function getGroupsFromFields(Entity $entity, array $fieldsToWatch): array
    {
        $requiredGroupIds = [];
        foreach ($fieldsToWatch as $field) {
            $relatedEntity = $entity->get($field);
            if ($relatedEntity && $relatedEntity->get('name')) {
                $group = $this->getGroupByName($relatedEntity->get('name'));
                if ($group) {
                    $requiredGroupIds[] = $group->getId();
                }
            }
        }

        return array_unique($requiredGroupIds);
    }

    private function setGroupsForEntity(Entity $entity, array $requiredGroupIds): void
    {
        $entity->setLinkMultipleIdList('teams', $requiredGroupIds);
        $entityType = $entity->getEntityType();
        $entityId = $entity->getId();
        $this->log->info("Setting group assignments [" . implode(', ', $requiredGroupIds) . "] for {$entityType} ID: {$entityId}");
    }

    private function getGroupByName(string $groupName): ?Entity
    {
        return $this->entityManager->getRepository('Team')->where(['name' => $groupName])->findOne();
    }
}
<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

class EntitySyncService {

    private const BASE_FIELDS_TO_WATCH = [
        'cVankoCRM',
        'firstName',
        'lastName',
        'emailAddress',
        'phoneNumber',
        'cTeamId',
        'cSlimFitCenterId'
    ];
    
    public function __construct(
        private readonly Config $config,
        private readonly Log $log,
        private readonly EntityManager $entityManager,
    ) {}

    public function syncFromLead(Entity $sourceEntity): void
    {
        $this->syncEntity($sourceEntity, destinationEntityType: 'Contact');
    }

    public function syncFromContact(Entity $sourceEntity): void
    {
        $this->syncEntity($sourceEntity, destinationEntityType: 'Lead');
    }

    private function syncEntity(Entity $sourceEntity, string $destinationEntityType): void
    {
        $changedFields = $this->getChangedFields($sourceEntity);

        if (!empty($changedFields)) {
            $destinationEntity = $this->getRelatedEntity($sourceEntity, $destinationEntityType);

            if ($destinationEntity) {
                $this->applyChanges($sourceEntity, $destinationEntity, $changedFields);
            }
        }
    }

    private function getChangedFields(Entity $entity): array
    {
        $changedFields = [];
        foreach (self::BASE_FIELDS_TO_WATCH as $field) {
            if ($entity->isAttributeChanged($field)) {
                $this->log->info('Field change detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId() . ' - field: ' . $field);
                $changedFields[] = $field;
            }
        }
        return $changedFields;
    }

    private function getRelatedEntity(Entity $sourceEntity, string $destinationEntityType): ?Entity
    {
        if (!$sourceEntity->get('cVankoCRM')) {
            return null;
        }
        return $this->entityManager->getRepository($destinationEntityType)
            ->where(['cVankoCRM' => $sourceEntity->get('cVankoCRM')])
            ->findOne();
    }
    
    private function applyChanges(Entity $sourceEntity, Entity $destinationEntity, array $fieldsToSync): void
    {
        $this->log->info('Syncing from "' . $sourceEntity->getEntityType() . '" to "' . $destinationEntity->getEntityType() . '"');
        $this->log->info('Syncing from "' . $sourceEntity->getId() . '" to "' . $destinationEntity->getId() . '"');
        
        foreach($fieldsToSync as $field){
            $this->log->info('Changing field "' . $field . '" for "' . $destinationEntity->getId() . '" from "' . $destinationEntity->get($field) . '" to "' . $sourceEntity->get($field) . '"');
            $destinationEntity->set($field, $sourceEntity->get($field));
        }

        $this->entityManager->saveEntity(
            $destinationEntity, 
            [
                'skipAfterSave' => true,
            ]
        );
    }
}
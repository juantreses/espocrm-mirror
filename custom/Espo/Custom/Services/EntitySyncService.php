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
        if($this->hasFieldsChanged($sourceEntity) && $this->getRelatedContact($sourceEntity)){
            $this->syncBaseData($sourceEntity, $this->getRelatedContact($sourceEntity));
        }
    }
    public function syncFromContact(Entity $sourceEntity): void
    {
        if($this->hasFieldsChanged($sourceEntity) && $this->getRelatedLead($sourceEntity)){
            $this->syncBaseData($sourceEntity, $this->getRelatedLead($sourceEntity));
        }
    }

    private function getRelatedLead(Entity $contact): ?Entity
    {
        if (!$contact->get('cVankoCRM')) {
            return null;
        }
        return $this->entityManager->getRepository('Lead')->where(['cVankoCRM' => $contact->get('cVankoCRM')])->findOne();
    }

    private function getRelatedContact(Entity $lead): ?Entity
    {
        if (!$lead->get('cVankoCRM')) {
            return null;
        }
        return $this->entityManager->getRepository('Contact')->where(['cVankoCRM' => $lead->get('cVankoCRM')])->findOne();
    }

    private function syncBaseData(Entity $sourceEntity, Entity $destinationEntity): void
    {
        $this->log->info('Syncing from "' . $sourceEntity->getEntityType() . '" to "' . $destinationEntity->getEntityType() . '"');
        $this->log->info('Syncing from "' . $sourceEntity->getId() . '" to "' . $destinationEntity->getId() . '"');
        foreach(self::BASE_FIELDS_TO_WATCH as $field){
            if($this->hasFieldChanged($sourceEntity, $field)){
                $this->log->info('Changing field "' . $field . '" for "' . $destinationEntity->getId() . '" from "' . $destinationEntity->get($field) . '" to "' . $sourceEntity->get($field) . '"');
                $destinationEntity->set($field, $sourceEntity->get($field));
            }
        }
        $this->entityManager->saveEntity(
            $destinationEntity, 
            [
                SaveOption::SKIP_ALL => true,
            ]
        );
    }

    private function hasFieldsChanged(Entity $entity): bool
    {
        $changed = false;
        foreach (self::BASE_FIELDS_TO_WATCH as $field) {
            $changed = $changed || $this->hasFieldChanged($entity, $field);
        }
        return $changed;
    }

    private function hasFieldChanged(Entity $entity, string $field): bool
    {
        if ($entity->isAttributeChanged($field)) {
            $this->log->info('Field changed detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId() . ' - field: ' . $field);
            return true;
        }
        return false;
    }
}
<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Custom\Logic\LeadStateMachine;
use Espo\Core\Exceptions\BadRequest;

class StatusValidator implements BeforeSave
{
    public function __construct(
        private readonly LeadStateMachine $stateMachine
    ) {}

    /**
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        // Only check if it's an existing record and the status has changed
        if ($entity->isNew() || !$entity->isAttributeChanged('status')) {
            return;
        }

        $oldStatus = $entity->getFetched('status');
        $newStatus = $entity->get('status');

        // Skip validation if old status is null (initial import/creation edge cases)
        if (empty($oldStatus)) {
            return;
        }

        if (!$this->stateMachine->canTransition($oldStatus, $newStatus)) {
            throw new BadRequest(
                sprintf(
                    "Invalid status transition from '%s' to '%s'. Allowed transitions: %s",
                    $oldStatus,
                    $newStatus,
                    implode(', ', $this->stateMachine->getAllowedNextStates($oldStatus))
                )
            );
        }
    }

    
}
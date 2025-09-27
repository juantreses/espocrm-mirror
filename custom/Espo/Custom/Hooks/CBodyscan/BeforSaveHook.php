<?php

namespace Espo\Custom\Hooks\CBodyscan;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;

class BeforeSaveHook implements BeforeSave
{
    private const FIELDS_TO_WATCH = [
        'cSlimFitCenter',
        'cTeam',
    ];
    public function __construct(
        private readonly Log $log,
        private readonly GroupAssignmentService $groupAssignmentService,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $this->groupAssignmentService->syncGroupsFromFields($entity, self::FIELDS_TO_WATCH, $entity->get('contact'));
        } catch (\Exception $e) {
            $this->log->error('Lead Before Save Hook error: ' . $e->getMessage());
        }
    }
}
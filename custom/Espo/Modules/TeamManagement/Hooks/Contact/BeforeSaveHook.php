<?php

namespace Espo\Custom\Hooks\Contact;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
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

    public function beforeSave(Entity $contact, SaveOptions $options): void
    {
        try {
            $this->groupAssignmentService->syncGroupsFromFields($contact, self::FIELDS_TO_WATCH);
        } catch (\Exception $e) {
            $this->log->error('Contact Before Save Hook error: ' . $e->getMessage());
        }
    }
}
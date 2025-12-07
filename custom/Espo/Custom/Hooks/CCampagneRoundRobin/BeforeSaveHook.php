<?php

namespace Espo\Custom\Hooks\CCampagneRoundRobin;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;

class BeforeSaveHook implements BeforeSave
{
    private const NAME_FIELDS_TO_WATCH = [
        'cSlimFitCenter',
        'campaign',
    ]; 
    private const GROUP_FIELDS_TO_WATCH = [
        'cSlimFitCenter',
    ];
    public function __construct(
        private readonly Log $log,
        private readonly GroupAssignmentService $groupAssignmentService,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $this->groupAssignmentService->syncGroupsFromFields($entity, self::GROUP_FIELDS_TO_WATCH);
            $this->setName($entity);
        } catch (\Exception $e) {
            $this->log->error('Lead Before Save Hook error: ' . $e->getMessage());
        }
    }

    private function setName(Entity $entity): void
    {
        $entity->Set('name',$entity->get('campaign')->get('cCampaignType'));
        $entity->Set('name',$entity->get('name') . " - " . $entity->get('campaign')->get('cCampaignSubType'));
        $entity->Set('name',$entity->get('name') . " - " . $entity->get('cSlimFitCenter')->get('name'));
    }
}
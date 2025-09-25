<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Log;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\Vanko\Services\LeadDataValidator;
use Espo\Modules\Vanko\Services\LeadFactory;
use Espo\Modules\Vanko\Services\TeamAssignmentService;
use Espo\Modules\Vanko\Services\SlimFitCenterAssignmentService;
use Espo\Modules\Vanko\Services\CampaignAssignmentService;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Orchestrates the processing of incoming leads from the Vanko API.
 * It coordinates validation, entity creation/updates, and assignments.
 */
class LeadService
{
    // Configuration constants
    private const COACH_FIELD = 'CC_SlimFitCenter_Coach';
    private const CENTER_FIELD = 'CC_SlimFitCenter';
    private const CAMPAIGN_FIELD = 'CC_SlimFitCenter_Campagne_Type';
    private const DEFAULT_LEAD_STATUS = 'assigned';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
        private readonly LeadDataValidator $validator,
        private readonly LeadFactory $leadFactory,
        private readonly TeamAssignmentService $teamAssigner,
        private readonly SlimFitCenterAssignmentService $centerAssigner,
        private readonly CampaignAssignmentService $campaignAssigner
    ) {}

    /**
     * @throws BadRequest
     * @throws Error
     */
    public function processLead(object $data): array
    {
        try {
            $this->validator->validate($data);

            $this->log->info("Processing lead for Vanko ID {$data->contact_id}");

            $lead = $this->findLeadByVankoId($data->contact_id);
            $action = 'updated';

            if ($lead === null) {
                $lead = $this->leadFactory->createFromData($data);
                if ($lead === null) {
                    throw new Error("Failed to create lead entity for Vanko ID {$data->contact_id}");
                }
                $action = 'created';
            } else {
                $lead = $this->leadFactory->updateFromData($lead, $data);
            }

            $this->handleAssignments($lead, $data);

            $this->entityManager->saveEntity(
                $lead, 
                [
                    'skipAfterSave' => $lead->isNew(),
                ]
            );
            $this->log->info("Successfully {$action} lead {$lead->getId()} for Vanko ID {$data->contact_id}");

            return $this->createResponse(
                success: true,
                action: $action,
                leadId: $lead->getId(),
                vankoId: $data->contact_id
            );

        } catch (BadRequest $e) {
            $this->log->error("Bad request processing lead: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->log->error("Error processing lead for Vanko ID {$data->contact_id}: " . $e->getMessage());
            throw new Error("Failed to process lead: " . $e->getMessage());
        }
    }
    
    private function handleAssignments(Lead $lead, object $data): void
    {
        $coachName = $this->getTrimmedValue($data, self::COACH_FIELD);
        $centerName = $this->getTrimmedValue($data, self::CENTER_FIELD);
        $campaignName = $this->getTrimmedValue($data, self::CAMPAIGN_FIELD);

        $teamWasAssigned = $this->teamAssigner->assignTeamByName($lead, $coachName);
        $this->centerAssigner->assignCenterByName($lead, $centerName);
        $this->campaignAssigner->assignCampaignByName($lead, $campaignName);

        if ($teamWasAssigned) {
            $lead->set('status', self::DEFAULT_LEAD_STATUS);
            $this->log->info("Set lead status to: " . self::DEFAULT_LEAD_STATUS);
        }
    }

    private function findLeadByVankoId(string $contactId): ?Lead
    {
        try {
            /** @var Lead|null */
            return $this->entityManager
                ->getRepository('Lead')
                ->where(['cVankoCRM' => $contactId])
                ->findOne();
        } catch (\Exception $e) {
            $this->log->error("Failed to find lead by Vanko ID {$contactId}: " . $e->getMessage());
            return null;
        }
    }
    
    private function getTrimmedValue(object $data, string $field): string
    {
        return isset($data->$field) ? trim((string) $data->$field) : '';
    }

    private function createResponse(bool $success, string $action, ?string $leadId, string $vankoId): array
    {
        return [
            'success' => $success,
            'action' => $action,
            'id' => $leadId,
            'vanko_id' => $vankoId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
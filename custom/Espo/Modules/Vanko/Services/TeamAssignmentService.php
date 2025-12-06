<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Custom\Logic\LeadStateMachine;
use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;
use Espo\Modules\Vanko\Services\Util\EntityFactory;

/**
 * Handles assigning a CTeam (Coach) to a Lead.
 */
class TeamAssignmentService
{
    private const ENTITY_TEAM = 'CTeam';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
        private readonly EntityFactory $entityFactory,
    ) {}

    /**
     * Assigns a CTeam to a lead based on the team's name.
     * If the name is empty, it un-assigns any existing team.
     * Returns true if a team was successfully assigned.
     */
    public function assignTeamByName(Lead $lead, string $teamName): void
    {
        if ($teamName === '') {
            $this->unassignTeam($lead);
            return;
        }

        $this->log->info("Processing team assignment for lead {$lead->getId()} with coach: {$teamName}");
        $team = $this->entityFactory->findOrCreate(self::ENTITY_TEAM, $teamName);

        if ($team === null) {
            $this->log->error("Could not find or create team '{$teamName}' for lead {$lead->getId()}.");
            return;
        }
        
        if ($lead->get('cTeamId') === $team->getId()) {
            $this->log->info("Lead {$lead->getId()} is already assigned to team {$teamName}.");
            // Even if already related, ensure status reflects assignment when appropriate
            $this->ensureAssignedStatus($lead);
            return;
        }

        $this->assignTeam($lead, $team);
    }

    public function assignTeam(Lead $lead, Entity $team): void
    {
        try {
            $this->entityManager->getRepository('Lead')->getRelation($lead, 'cTeam')->relate($team);
            $this->log->info("Assigned team {$team->getId()} to lead {$lead->getId()}");
            // Ensure status reflects assignment for all flows (including Round Robin)
            $this->ensureAssignedStatus($lead);
        } catch (\Exception $e) {
            $this->log->error("Failed to assign team {$team->getId()} to lead {$lead->getId()}: " . $e->getMessage());
        }
    }

    private function unassignTeam(Lead $lead): void
    {
        if (empty($lead->get('cTeamId'))) {
            return;
        }
        
        try {
            $this->entityManager->getRepository('Lead')->getRelation($lead, 'cTeam')->unrelate($lead->get('cTeam'));
            $this->log->info("Removed team relationship from lead {$lead->getId()}");
        } catch (\Exception $e) {
            $this->log->error("Failed to remove team relationship from lead {$lead->getId()}: " . $e->getMessage());
        }
    }

    /**
     * Ensure lead status transitions from empty/new to assigned when a team is present.
     * Does not override any other non-new statuses.
     */
    private function ensureAssignedStatus(Lead $lead): void
    {
        $currentStatus = (string) ($lead->get('status') ?? '');
        if ($currentStatus === '' || $currentStatus === LeadStateMachine::STATE_NEW) {
            $lead->set('status', LeadStateMachine::STATE_ASSIGNED);
            $this->log->info("Set lead {$lead->getId()} status to 'assigned' due to team assignment.");
        }
    }
}
<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

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
    public function assignTeamByName(Lead $lead, string $teamName): bool
    {
        if ($teamName === '') {
            $this->unassignTeam($lead);
            return false;
        }

        $this->log->info("Processing team assignment for lead {$lead->getId()} with coach: {$teamName}");
        $team = $this->entityFactory->findOrCreate(self::ENTITY_TEAM, $teamName);

        if ($team === null) {
            $this->log->error("Could not find or create team '{$teamName}' for lead {$lead->getId()}.");
            return false;
        }
        
        if ($lead->get('cTeamId') === $team->getId()) {
            $this->log->info("Lead {$lead->getId()} is already assigned to team {$teamName}.");
            return false;
        }

        return $this->assignTeam($lead, $team);
    }

    public function assignTeam(Lead $lead, Entity $team): bool
    {
        try {
            $this->entityManager->getRepository('Lead')->getRelation($lead, 'cTeam')->relate($team);
            $this->log->info("Assigned team {$team->getId()} to lead {$lead->getId()}");
            return true;
        } catch (\Exception $e) {
            $this->log->error("Failed to assign team {$team->getId()} to lead {$lead->getId()}: " . $e->getMessage());
            return false;
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
}
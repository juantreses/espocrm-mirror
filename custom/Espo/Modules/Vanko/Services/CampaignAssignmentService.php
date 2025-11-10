<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;
use Espo\Modules\Vanko\Services\Util\EntityFactory;
use Espo\Modules\Vanko\Services\TeamAssignmentService;
use Espo\Custom\Services\RoundRobinLeadService; 

/**
 * Handles assigning a Campaign to a Lead and applying RoundRobin logic.
 */
class CampaignAssignmentService
{
    private const ENTITY_CAMPAIGN = 'Campaign';
    private const ENTITY_CAMPAIGN_ROUNDROBIN = "CCampagneRoundRobin";
    private const ENTITY_LEAD = 'Lead';
    private const ENTITY_TEAM = 'CTeam';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
        private readonly EntityFactory $entityFactory,
        private readonly TeamAssignmentService $teamAssignmentService,
        private readonly RoundRobinLeadService $roundRobinLeadService,

    ) {}

    public function assignCampaignByName(Lead $lead, string $campaignName): void
    {
        if ($campaignName === '') {
            $this->log->info("No campaign name provided for lead {$lead->getId()}, skipping assignment.");
            return;
        }
        $this->log->info("Processing Campaign assignment for lead {$lead->getId()} with campaign: {$campaignName}");
        $campaign = $this->findOrCreateCampaign($lead, $campaignName);
        if ($campaign === null) {
            return; // Error already logged inside findOrCreateCampaign
        }
        if ($lead->get('campaignId') !== $campaign->getId()) {
            $this->assignCampaign($lead, $campaign);
        } else {
            $this->log->info("Lead {$lead->getId()} is already assigned to campaign {$campaignName}.");
        }
        $this->applyRoundRobinLogic($lead, $campaign);
    }

    private function findOrCreateCampaign(Lead $lead, string $campaignName): ?Entity
    {
        $campaign = $this->entityFactory->findOrCreate(
            self::ENTITY_CAMPAIGN,
            $campaignName,
            ['status' => 'Active']
        );

        if ($campaign === null) {
            $this->log->error("Failed to find or create campaign '{$campaignName}' for lead {$lead->getId()}");
        }
        return $campaign;
    }

    private function assignCampaign(Lead $lead, Entity $campaign): void
    {
        try {
            $lead->set('campaignId',$campaign->getId());
            $this->log->info("Assigned Campaign {$campaign->getId()} to lead {$lead->getId()}");
        } catch (\Exception $e) {
            $this->log->error("Failed to assign Campaign {$campaign->getId()} to lead {$lead->getId()}: " . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------------
    // ROUND ROBIN LOGIC
    // ---------------------------------------------------------------------------------

    private function applyRoundRobinLogic(Lead $lead, Entity $campaign): void
    {
        try {
            $teamId = $lead->get('cTeamId');
            if ($teamId) {
                $this->log->warning("Cannot apply RoundRobin for lead {$lead->getId()}: already has team: " . $teamId . ".");
                return;
            }

            $roundRobin = $this->findActiveRoundRobin($campaign, $lead);
            if (!$roundRobin) {
                return;
            }

            $activeMembersIds = $roundRobin->getLinkMultipleIdList('activeMembers') ?? [];
            if (empty($activeMembersIds)) {
                $this->log->warning("RoundRobin {$roundRobin->getId()} is active but has no members.");
                return;
            }

            $memberToAssignId = $this->getMemberIdWithFewestLeads($roundRobin, $activeMembersIds);

            $team = $this->entityManager->getRepository(self::ENTITY_TEAM)
                ->where(['id' => $memberToAssignId])
                ->findOne();
            
            if (!$team) {
                 $this->log->error("Failed to find team entity with ID: {$memberToAssignId}. Cannot assign lead.");
                 return;
            }

            $this->log->info("RoundRobin determined that member lead {$lead->get('name')} should be assigned to {$team->get('name')}  .");

            $lead->set('cCampagneRoundRobinId', $roundRobin->get('id'));
            $this->teamAssignmentService->assignTeam($lead, $team);

        } catch (\Exception $e) {
            $this->log->error("Failed to apply RoundRobin logic for Campaign {$campaign->getId()} to lead {$lead->getId()}: " . $e->getMessage());
        }
    }

    private function findActiveRoundRobin(Entity $campaign, Lead $lead): ?Entity
    {
        $campaignId = $campaign->getId();
        $centerId = $lead->get('cSlimFitCenterId');

        if (!$campaignId || !$centerId) {
            $this->log->warning("Cannot find RoundRobin for lead {$lead->getId()}: missing Campaign or SlimFitCenter ID.");
            return null;
        }

        $roundRobin = $this->entityManager->getRepository(self::ENTITY_CAMPAIGN_ROUNDROBIN)
            ->where(['campaignId' => $campaignId, 'slimFitCenterId' => $centerId])
            ->findOne();

        if (!$roundRobin) {
            $this->log->warning("Cannot find RoundRobin for campaign {$campaignId} and SFC {$centerId}");
            return null;
        }

        if (!$roundRobin->get('roundRobinActief')) {
            $roundRobinId = $roundRobin->getId();
            $this->log->warning("Round Robin {$roundRobinId} is not active");
            return null;
        }

        return $roundRobin;
    }

    private function getMemberIdWithFewestLeads(Entity $roundRobin, array $activeMembersIds): ?string
    {
        $memberLeadCounts = [];
        $roundRobinStart = $roundRobin->get('roundRobinStart');
        $roundRobinId = $roundRobin->getId();

        foreach ($activeMembersIds as $memberId) {
            $count = $this->roundRobinLeadService->getLeadCountForTeam(
                $roundRobinId,
                $memberId,
                $roundRobinStart
            );
            $memberLeadCounts[$memberId] = $count;
        }

        asort($memberLeadCounts);
        
        return key($memberLeadCounts);
    }

}
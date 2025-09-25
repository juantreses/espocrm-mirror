<?php

namespace Espo\Custom\Services;

use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;

class RoundRobinStatisticsService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    public function getMemberstats(Entity $roundRobin): array
    {
        $roundRobinStartDate = $roundRobin->get('roundRobinStart');

        // If there's no start date, we can't filter, so return an empty result.
        if (empty($roundRobinStartDate)) {
            return ['members' => [], 'totalLeads' => 0];
        }

        $teamRepo = $this->entityManager->getRepository('CTeam');
        $activeMemberIds = $roundRobin->getLinkMultipleIdList('activeMembers');
        $teams = !empty($activeMemberIds) ? $teamRepo->where(['id' => $activeMemberIds])->find() : [];

        $memberStats = [];
        $totalLeads = 0;

        foreach ($teams as $team) {
            $leadCount = $this->entityManager
                ->getRepository('Lead')
                ->where([
                    'cCampagneRoundRobinId' => $roundRobin->getId(),
                    'cTeamId' => $team->getId(),
                    'cExternalCreatedAt>=' => $roundRobinStartDate,
                ])
                ->count();
            
            $memberStats[] = [
                'teamId' => $team->getId(),
                'memberName' => $team->get('name'),
                'leadCount' => $leadCount,
            ];

            $totalLeads += $leadCount;
        }

        return [
            'members'    => $memberStats,
            'totalLeads' => $totalLeads,
        ];
    }
}
<?php

declare(strict_types=1);

namespace Espo\Custom\Services;

use Espo\ORM\EntityManager;

class RoundRobinLeadService
{
    private const ENTITY_LEAD = 'Lead';

    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    /**
     * Retrieves the count of leads assigned to a specific team under a specific Round Robin,
     * filtered by the round robin's start date.
     */
    public function getLeadCountForTeam(string $roundRobinId, string $teamId, string $startDate): int
    {
        return $this->entityManager->getRepository(self::ENTITY_LEAD)
            ->where([
                'cCampagneRoundRobinId' => $roundRobinId,
                'cTeamId' => $teamId,
                'cExternalCreatedAt>=' => $startDate,
            ])
            ->count();
    }
}
<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Controllers\Base;

class CCampagneRoundRobin extends Base
{
    /**
     * Get statistics for a round robin
     * * GET /api/v1/CCampagneRoundRobin/{id}/member-stats
     */
    public function actionMemberStats(Request $request, Response $response): array
    {
        $roundRobinId = $request->getRouteParam('id');
        
        $roundRobin = $this->getEntityManager()->getEntity('CCampagneRoundRobin', $roundRobinId);

        if (!$roundRobin) {
            throw new NotFound("CCampagneRoundRobin with ID '{$roundRobinId}' not found.");
        }

        /** @var RoundRobinStatisticsService $statsService */
        $statsService = $this->getServiceFactory()->create('RoundRobinStatisticsService');

        return $statsService->getMemberstats($roundRobin);
    }
}

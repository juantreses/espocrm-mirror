<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Services\CTeamStatisticsService;

class CTeam extends \Espo\Core\Templates\Controllers\Base
{
    /**
     * Get statistics for a specific team
     * 
     * GET /api/v1/CTeam/{id}/statistics?startDate=2024-01-01&endDate=2024-01-31
     */
    public function getActionStatistics(Request $request, Response $response): array
    {
        $id = $request->getRouteParam('id');
        
        if (!$id) {
            throw new BadRequest('Team ID is required');
        }

        $team = $this->getRecordService()->getEntity($id);

        if (!$team) {
            throw new NotFound('Team not found');
        }

        $startDate = $request->getQueryParam('startDate');
        $endDate = $request->getQueryParam('endDate');

        try {

            $statsService = $this->getServiceFactory()->create('CTeamStatisticsService');

            $statistics = $statsService->getTeamStatistics($id, $startDate, $endDate);

            return [
                'success' => true,
                'data' => $statistics,
            ];

        } catch (\Exception $e) {
            $GLOBALS['log']->error('CTeam Statistics Error: ' . $e->getMessage(), [
                'teamId' => $id,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new BadRequest('Failed to get team statistics: ' . $e->getMessage());
        }
    }
}

<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Custom\Enums\LeadEventType;

class CTeamStatisticsService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    public function getTeamStatistics(string $teamId, ?string $startDate = null, ?string $endDate = null): array
    {
        $team = $this->fetchTeam($teamId);

        if (!$team) {
            throw new NotFound('Team not found');
        }

        $dateFilter = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        return [
            'teamInfo' => [
                'id' => $team->getId(),
                'name' => $team->get('name'),
            ],
            'period' => $dateFilter,
            'leadStatistics' => $this->getLeadStatistics($team, $dateFilter),
            'leadEventStatistics' => $this->getLeadEventStatistics($team, $dateFilter),
            'memberStatistics' => $this->getConvertedByContactType($team, $dateFilter),
        ];
    }

    private function getLeadStatistics(Entity $team, array $dateFilter): array
    {
        $totalLeads = $this->entityManager
            ->getRDBRepository('Lead')
            ->leftJoin('cTeam')
            ->where($this->buildLeadDateWhereClause($team, $dateFilter))
            ->count();

        return [
            'totalLeads' => $totalLeads,
        ];
    }

    private function getLeadEventStatistics(Entity $team, array $dateFilter): array
    {
        $eventCounts = $this->getEventTypeBreakdown($team, $dateFilter);
        $calledLeads = $this->getUniqueLeadsCalled($team, $dateFilter);
        $invitedLeads = $this->getUniqueLeadsWithEventType(LeadEventType::INVITED, $team, $dateFilter);
        $appointmentBookedLeads = $this->getUniqueLeadsWithEventType(LeadEventType::APPOINTMENT_BOOKED, $team, $dateFilter);
        $attendedLeads = $this->getUniqueLeadsWithEventType(LeadEventType::ATTENDED, $team, $dateFilter);
        $noShowLeads = $this->getUniqueLeadsWithEventType(LeadEventType::NO_SHOW, $team, $dateFilter);
        $convertedLeads = $this->getUniqueLeadsWithEventType(LeadEventType::CONVERTED, $team, $dateFilter);

        return [
            'eventTypeBreakdown' => $eventCounts,
            'uniqueLeadsCalled' => $calledLeads,
            'uniqueInvited' => $invitedLeads,
            'uniqueAppointmentBooked' => $appointmentBookedLeads,
            'uniqueAttended' => $attendedLeads,
            'uniqueNoShow' => $noShowLeads,
            'uniqueConvertedLeads' => $convertedLeads,
        ];
    }

    private function getEventTypeBreakdown(Entity $team, array $dateFilter): array
    {
        $qb = $this->entityManager->getQueryBuilder()
            ->select(['cle.eventType', ['COUNT:(id)', 'eventCount']])
            ->from('CLeadEvent', 'cle')
            ->leftJoin('lead', 'l')
            ->leftJoin(
                'cTeam', 
                'ct',
                [
                    'l.cTeamId:' => 'ct.id',
                    'ct.deleted' => false,
                ],
            )
            ->where($this->buildLeadEventDateWhereClause($team, $dateFilter))
            ->groupBy(['eventType']);

        $rows = $this->entityManager
            ->getQueryExecutor()
            ->execute($qb->build())
            ->fetchAll(\PDO::FETCH_ASSOC);

        $eventCounts = [];
        foreach ($rows as $row) {
            $eventType = $row['cle.eventType'];
            $eventCounts[$eventType] = (int) $row['eventCount'];
        }

        return $eventCounts;
    }

    private function getUniqueLeadsCalled(Entity $team, array $dateFilter): int
    {
        // Build JOIN conditions for CLeadEvent with event filters in the JOIN
        $joinCleConditions = [
            'cle.leadId:' => 'l.id',
            'cle.deleted' => false,
            'cle.eventType' => LeadEventType::CALLED->value,
        ];
        
        // Add date filters to JOIN conditions
        $joinCleConditions = array_merge($joinCleConditions, $this->buildEventDateConditions($dateFilter, 'cle'));

        $qb = $this->entityManager->getQueryBuilder()
            ->select(['l.id'])
            ->from('Lead', 'l')
            ->join('CLeadEvent', 'cle', $joinCleConditions)
            ->leftJoin(
                'cTeam',
                'ct',
                [
                    'l.cTeamId:' => 'ct.id',
                    'ct.deleted' => false,
                ],
            )
            ->where([
                'ct.id' => $team->getId(),
                'l.deleted' => false,
                'l.cCallCount>' => 0,
            ])
            ->groupBy('l.id');

        $rows = $this->entityManager
            ->getQueryExecutor()
            ->execute($qb->build())
            ->fetchAll(\PDO::FETCH_ASSOC);

        return count($rows);
    }

    private function getUniqueLeadsWithEventType(LeadEventType $eventType, Entity $team, array $dateFilter): int
    {
        $whereClause = $this->buildLeadEventDateWhereClause($team, $dateFilter);
        $whereClause['eventType'] = $eventType->value;

        $qb = $this->entityManager->getQueryBuilder()
            ->select('l.id')
            ->from('CLeadEvent', 'cle')
            ->leftJoin('lead', 'l')
            ->leftJoin(
                'cTeam',
                'ct',
                [
                    'l.cTeamId:' => 'ct.id',
                    'ct.deleted' => false,
                ],
            )
            ->where($whereClause)
            ->groupBy('l.id');

        $rows = $this->entityManager
            ->getQueryExecutor()
            ->execute($qb->build())
            ->fetchAll(\PDO::FETCH_ASSOC);

        $ids = array_column($rows, 'l.id');

        return count(array_unique($ids));
    }

    private function getConvertedByContactType(Entity $team, array $dateFilter): array
    {
        $joinCleConditions = [
            'cle.leadId:' => 'l.id',
            'cle.deleted' => false,
            'cle.eventType' => LeadEventType::CONVERTED->value,
        ];
        
        // Add date filters to JOIN conditions
        $joinCleConditions = array_merge($joinCleConditions, $this->buildEventDateConditions($dateFilter, 'cle'));

        $qb = $this->entityManager->getQueryBuilder()
            ->select(['c.cTypeKlant', 'l.id'])
            ->from('Contact', 'c')
            ->join('Lead', 'l', [
                'c.id:' => 'l.createdContactId',
            ])
            ->join('CLeadEvent', 'cle', $joinCleConditions)
            ->leftJoin('cTeam', 'ct', [
                'l.cTeamId:' => 'ct.id',
                'ct.deleted' => false,
            ])
            ->where([
                'ct.id' => $team->getId(),
                'l.deleted' => false,
                'c.deleted' => false,
            ])
            ->groupBy(['c.cTypeKlant', 'l.id']);

        $rows = $this->entityManager
            ->getQueryExecutor()
            ->execute($qb->build())
            ->fetchAll(\PDO::FETCH_ASSOC);

        // Aggregate unique leads per contact type
        $byType = [];
        foreach ($rows as $row) {
            $type = $row['c.cTypeKlant'] ?? null;
            if ($type === null || $type === '') {
                $type = 'Unknown';
            }
            if (!isset($byType[$type])) {
                $byType[$type] = 0;
            }
            $byType[$type] += 1; // one row per unique (type, lead)
        }

        ksort($byType);
        return $byType;
    }

    /**
     * Build WHERE clause for Lead entities (uses cExternalCreatedAt)
     */
    private function buildLeadDateWhereClause(Entity $team, array $dateFilter): array
    {
        $whereClause = [
            'cTeam.id' => $team->getId(),
            'deleted' => false
        ];

        if (!empty($dateFilter['startDate'])) {
            $whereClause['cExternalCreatedAt>='] = $dateFilter['startDate'] . ' 00:00:00';
        }

        if (!empty($dateFilter['endDate'])) {
            $whereClause['cExternalCreatedAt<='] = $dateFilter['endDate'] . ' 23:59:59';
        }

        return $whereClause;
    }

    /**
     * Build WHERE clause for CLeadEvent entities (uses eventDate)
     */
    private function buildLeadEventDateWhereClause(Entity $team, array $dateFilter): array
    {
        $whereClause = [
            'ct.id' => $team->getId(),
            'cle.deleted' => false,
            'l.deleted' => false,
        ];

        if (!empty($dateFilter['startDate'])) {
            $whereClause['cle.eventDate>='] = $dateFilter['startDate'] . ' 00:00:00';
        }

        if (!empty($dateFilter['endDate'])) {
            $whereClause['cle.eventDate<='] = $dateFilter['endDate'] . ' 23:59:59';
        }

        return $whereClause;
    }

    /**
     * Build date conditions for JOIN clauses (uses eventDate)
     */
    private function buildEventDateConditions(array $dateFilter, string $prefix = 'cle'): array
    {
        $conditions = [];

        if (!empty($dateFilter['startDate'])) {
            $conditions[$prefix . '.eventDate>='] = $dateFilter['startDate'] . ' 00:00:00';
        }

        if (!empty($dateFilter['endDate'])) {
            $conditions[$prefix . '.eventDate<='] = $dateFilter['endDate'] . ' 23:59:59';
        }

        return $conditions;
    }

    /**
     * Fetch team entity
     */
    private function fetchTeam(string $teamId): ?Entity
    {
        return $this->entityManager->getEntity('CTeam', $teamId);
    }
}
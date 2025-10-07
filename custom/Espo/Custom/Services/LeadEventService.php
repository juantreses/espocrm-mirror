<?php

declare(strict_types=1);

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\MessageSentOutcome;

class LeadEventService
{
    // Mapping FE outcome -> events to create
    private const CALL_OUTCOME_EVENT_MAP = [
        CallOutcome::CALLED->value => [LeadEventType::CALLED],
        CallOutcome::INVITED->value => [LeadEventType::CALLED, LeadEventType::INVITED],
        CallOutcome::CALL_AGAIN->value => [LeadEventType::CALLED, LeadEventType::CALL_AGAIN],
        CallOutcome::NO_ANSWER->value => [LeadEventType::CALLED, LeadEventType::NO_ANSWER],
        CallOutcome::WRONG_NUMBER->value => [LeadEventType::CALLED, LeadEventType::WRONG_NUMBER],
        CallOutcome::NOT_INTERESTED->value => [LeadEventType::CALLED, LeadEventType::NOT_INTERESTED],
    ];

    // Mapping FE outcome -> events to create
    private const KICKSTART_OUTCOME_EVENT_MAP = [
        KickstartOutcome::BECAME_CLIENT->value => [LeadEventType::ATTENDED, LeadEventType::BECAME_CLIENT],
        KickstartOutcome::NO_SHOW->value => [LeadEventType::NO_SHOW],
        KickstartOutcome::NOT_CONVERTED->value => [LeadEventType::ATTENDED, LeadEventType::NOT_CONVERTED],
        KickstartOutcome::STILL_THINKING->value => [LeadEventType::ATTENDED, LeadEventType::STILL_THINKING],
    ];

    private const KICKSTART_FOLLOW_UP_OUTCOME_EVENT_MAP = [
        KickstartOutcome::BECAME_CLIENT->value => [LeadEventType::BECAME_CLIENT],
        KickstartOutcome::NOT_CONVERTED->value => [LeadEventType::NOT_CONVERTED],
    ];

    private const MESSAGE_OUTCOME_EVENT_MAP = [
        MessageSentOutcome::NOT_INTERESTED->value => [LeadEventType::NOT_INTERESTED],
        MessageSentOutcome::CALL_AGAIN->value => [LeadEventType::CALL_AGAIN],
    ];

    private const STATUS_MAP = [
        LeadEventType::NO_ANSWER->value => LeadEventType::CALL_AGAIN->value,
        LeadEventType::CALL_AGAIN->value => LeadEventType::CALL_AGAIN->value,
        LeadEventType::WRONG_NUMBER->value => LeadEventType::WRONG_NUMBER->value,
        LeadEventType::NOT_INTERESTED->value => LeadEventType::NOT_INTERESTED->value,
        LeadEventType::INVITED->value => LeadEventType::INVITED->value,
        LeadEventType::APPOINTMENT_BOOKED->value => LeadEventType::APPOINTMENT_BOOKED->value,
        LeadEventType::ATTENDED->value => LeadEventType::ATTENDED->value,
        LeadEventType::APPOINTMENT_CANCELLED->value => LeadEventType::APPOINTMENT_CANCELLED->value,
        LeadEventType::BECAME_CLIENT->value => LeadEventType::BECAME_CLIENT->value,
        LeadEventType::NOT_CONVERTED->value => LeadEventType::NOT_CONVERTED->value,
        LeadEventType::STILL_THINKING->value => LeadEventType::STILL_THINKING->value,
        LeadEventType::NO_SHOW->value => LeadEventType::NO_SHOW->value,
        LeadEventType::BECAME_COACH->value => LeadEventType::BECAME_COACH->value,
        LeadEventType::MESSAGE_TO_BE_SENT->value => LeadEventType::MESSAGE_TO_BE_SENT->value,
        LeadEventType::MESSAGE_SENT->value => LeadEventType::MESSAGE_SENT->value,
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {}

    public function logEvent(string $leadId, LeadEventType $eventType, ?string $eventDate = null): array
    {
        $lead = $this->fetchLead($leadId);
        
        if (!$lead) {
            throw new NotFound('Lead not found');
        }

        $eventRepository = $this->entityManager->getRepository('CLeadEvent');
        $event = $this->entityManager->getEntity('CLeadEvent');

        $localTimezone = new \DateTimeZone('Europe/Brussels');
        $utcTimezone = new \DateTimeZone('UTC');

        if (!$eventDate) {
            $dt = new \DateTime('now', $localTimezone);
            $dt->setTimezone($utcTimezone);
        } else {
            $dt = new \DateTime($eventDate, $localTimezone);
            $dt->setTimezone($utcTimezone);
        }
        $event->set([
            'eventType' => $eventType->value,
            'eventDate' => $dt->format('Y-m-d H:i:s'),
        ]);

        $this->entityManager->saveEntity($event);
        $eventRepository->getRelation($event, 'lead')->relate($lead);
        $this->entityManager->saveEntity($event);

        $this->updateLeadStatus($lead, $eventType);

        return [
            'success' => true,
            'eventId' => $event->getId(),
            'eventType' => $eventType->value,
            'leadStatus' => $lead->get('status'),
        ];
    }

    public function logCall(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        $outcome = CallOutcome::from($data->outcome);
        $eventDate = $data->callDateTime ?? null;
        $callAgainDateTime = $data->callAgainDateTime ?? null;
        $coachNote = $data->coachNote ?? null;

        if (!isset(self::CALL_OUTCOME_EVENT_MAP[$outcome->value])) {
            throw new BadRequest('Invalid call outcome: ' . $outcome->value);
        }

        $this->incrementCallCount($leadId);

        $eventIds = [];

        foreach (self::CALL_OUTCOME_EVENT_MAP[$outcome->value] as $eventType) {
            $eventIds[] = $this->logEvent($leadId, $eventType, $eventDate)['eventId'];
        }

        if ($outcome->value === CallOutcome::CALL_AGAIN->value && $callAgainDateTime) {
            $this->addFollowupAction($leadId, $callAgainDateTime);
        } else {
            $this->clearFollowupAction($leadId);
        }

        if ($coachNote) {
            $source = 'Telefoon';
            $this->addCoachNote($leadId, $coachNote, $source, $eventDate);
        }

        return [
            'success' => true,
            'eventIds' => $eventIds,
        ];
    }

    public function logKickstart(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        $outcome = KickstartOutcome::from($data->outcome);
        $eventDate = $data->kickstartDateTime ?? null;
        $callAgainDateTime = $data->callAgainDateTime ?? null;
        $coachNote = $data->coachNote ?? null;

        if (!isset(self::KICKSTART_OUTCOME_EVENT_MAP[$outcome->value])) {
            throw new BadRequest('Invalid kickstart outcome: ' . $outcome->value);
        }

        $eventIds = [];
        foreach (self::KICKSTART_OUTCOME_EVENT_MAP[$outcome->value] as $eventType) {
            $eventIds[] = $this->logEvent($leadId, $eventType, $eventDate)['eventId'];
        }

        if ($outcome->value === KickstartOutcome::STILL_THINKING->value && $callAgainDateTime) {
            $this->addFollowupAction($leadId, $callAgainDateTime, 'KS twijfel - Opvolging');
        } else {
            $this->clearFollowupAction($leadId);
        }

        if ($coachNote) {
            $source = 'Kickstart';
            $this->addCoachNote($leadId, $coachNote, $source, $eventDate);
        }

        return [
            'success' => true,
            'eventIds' => $eventIds,
        ];
    }

    public function logKickstartFollowUp(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        $outcome = KickstartOutcome::from($data->outcome);
        $eventDate = $data->kickstartDateTime ?? null;
        $coachNote = $data->coachNote ?? null;
        
        if (!isset(self::KICKSTART_FOLLOW_UP_OUTCOME_EVENT_MAP[$outcome->value])) {
            throw new BadRequest('Invalid kickstart follow-up outcome: ' . $outcome->value);
        }

        $eventIds = [];
        foreach (self::KICKSTART_FOLLOW_UP_OUTCOME_EVENT_MAP[$outcome->value] as $eventType) {
            $eventIds[] = $this->logEvent($leadId, $eventType, $eventDate)['eventId'];
        }

        if ($coachNote) {
            $source = 'KS - Opvolging';
            $this->addCoachNote($leadId, $coachNote, $source, $eventDate);
        }

        $this->clearFollowupAction($leadId);

        return [
            'success' => true,
            'eventIds' => $eventIds,
        ];
        
    }

    public function logMessageSent(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        $result = $this->logEvent($leadId, LeadEventType::MESSAGE_SENT);
        return $result;
    }

    public function logMessageOutcome(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        $outcome = MessageSentOutcome::from($data->outcome);
        $callAgainDateTime = $data->callAgainDateTime ?? null;
        $coachNote = $data->coachNote ?? null;

        if (!isset(self::MESSAGE_OUTCOME_EVENT_MAP[$outcome->value])) {
            throw new BadRequest('Invalid message sent outcome: ' . $outcome->value);
        }

        $eventIds = [];
        foreach (self::MESSAGE_OUTCOME_EVENT_MAP[$outcome->value] as $eventType) {
            $eventIds[] = $this->logEvent($leadId, $eventType)['eventId'];
        }

        if ($outcome->value === MessageSentOutcome::CALL_AGAIN->value && $callAgainDateTime) {
            $this->addFollowupAction($leadId, $callAgainDateTime);
        } else {
            $this->clearFollowupAction($leadId);
        }

        if ($coachNote) {
            $source = 'Bericht';
            $this->addCoachNote($leadId, $coachNote, $source);
        }

        return [
            'success' => true,
            'eventIds' => $eventIds,
        ];

        return ['success' => true];
    }

    private function fetchLead(string $leadId): ?Entity
    {
        return $this->entityManager->getEntity('Lead', $leadId);
    }

    private function addCoachNote(string $leadId, string $coachNote, string $source, ?string $eventDate = null): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        if (!$eventDate) {
            $eventDate = date('Y-m-d H:i:s');
        }

        $timezone = new \DateTimeZone('Europe/Brussels');
        if (!$eventDate) {
            $dt = new \DateTime('now', $timezone);
        } else {
            $dt = new \DateTime($eventDate, $timezone);
        }

        $existingNotes = (string) ($lead->get('cNotes') ?? '');

        $formattedHeader = $dt->format('[d/m/Y H:i]');
        $newLine = "$formattedHeader ($source): $coachNote";
        $updatedNotes = $existingNotes ? ($existingNotes . "\n\n" . $newLine) : $newLine;

        $lead->set('cNotes', $updatedNotes);
        $this->entityManager->saveEntity($lead);
    }

    private function addFollowupAction(string $leadId, string $callAgainDateTime, string $followUpNote = 'Opnieuw bellen'): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        $dt = new \DateTime($callAgainDateTime);
        $formatted = $dt->format('d/m/Y H:i');
        $line = "$followUpNote: $formatted";
        $lead->set('cFollowUpAction', $line);
        $this->entityManager->saveEntity($lead);
    }

    private function clearFollowupAction(string $leadId): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        $lead->set('cFollowUpAction', '');
        $this->entityManager->saveEntity($lead);
    }

    private function incrementCallCount(string $leadId): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }
        
        $currentCount = $lead->get('cCallCount') ?? 0;
        $newCount = $currentCount + 1;
        $lead->set('cCallCount', $newCount);
        $this->entityManager->saveEntity($lead);
    }

    private function updateLeadStatus(Entity $lead, LeadEventType $eventType): void
    {
        if ($eventType->value === LeadEventType::NO_ANSWER->value) {
            $team = $lead->get('cTeam');
            $maxCallAttempts = $team->get('maxCallAttempts') ?? 3;
            $currentCallCount = $lead->get('cCallCount') ?? 0;

            if ($currentCallCount >= $maxCallAttempts) {
                $lead->set('status', LeadEventType::MESSAGE_TO_BE_SENT->value);
                $this->entityManager->saveEntity($lead);
                return;
            }
        }
    
        if (isset(self::STATUS_MAP[$eventType->value])) {
            $lead->set('status', self::STATUS_MAP[$eventType->value]);
            $this->entityManager->saveEntity($lead);
        }
    }
}
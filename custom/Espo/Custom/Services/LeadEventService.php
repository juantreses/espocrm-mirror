<?php

declare(strict_types=1);

namespace Espo\Custom\Services;

use DateTime;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Logic\LeadStateMachine;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\MessageSentOutcome;
use Exception;
use StdClass;

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
        LeadEventType::NO_ANSWER->value => LeadStateMachine::STATE_CALL_AGAIN,
        LeadEventType::CALL_AGAIN->value => LeadStateMachine::STATE_CALL_AGAIN,
        LeadEventType::WRONG_NUMBER->value => LeadStateMachine::STATE_DEAD,
        LeadEventType::NOT_INTERESTED->value => LeadStateMachine::STATE_DISQUALIFIED,
        LeadEventType::INVITED->value => LeadStateMachine::STATE_INVITED,
        LeadEventType::APPOINTMENT_BOOKED->value => LeadStateMachine::STATE_APPOINTMENT_BOOKED,
        LeadEventType::APPOINTMENT_CANCELLED->value => LeadStateMachine::STATE_APPOINTMENT_CANCELLED,
        LeadEventType::BECAME_CLIENT->value => LeadStateMachine::STATE_BECAME_CLIENT,
        LeadEventType::NOT_CONVERTED->value => LeadStateMachine::STATE_DISQUALIFIED,
        LeadEventType::STILL_THINKING->value => LeadStateMachine::STATE_STILL_THINKING,
        LeadEventType::NO_SHOW->value => LeadStateMachine::STATE_MESSAGE_TO_BE_SENT,
        LeadEventType::MESSAGE_TO_BE_SENT->value => LeadStateMachine::STATE_MESSAGE_TO_BE_SENT,
        LeadEventType::MESSAGE_SENT->value => LeadStateMachine::STATE_MESSAGE_SENT,
    ];

    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    /**
     * Persist a lead event and update lead status accordingly.
     * @throws Exception
     */
    public function logEvent(string $leadId, LeadEventType $eventType, ?string $eventDate = null): array
    {
        $lead = $this->fetchLead($leadId);
        
        if (!$lead) {
            throw new NotFound('Lead not found');
        }

        $eventRepository = $this->entityManager->getRepository('CLeadEvent');
        $event = $this->entityManager->getEntity('CLeadEvent');

        $timezone = new DateTimeZone('UTC');
        if (!$eventDate) {
            $dt = new DateTime('now', $timezone);
        } else {
            $dt = new DateTime($eventDate);
            $dt->setTimezone($timezone);
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

    /**
     * Log a call outcome and manage follow-up and coach note.
     * @throws BadRequest
     * @throws Exception
     */
    public function logCall(StdClass $data): array
    {
        $leadId = (string) $data->id;
        $rawOutcome = $data->outcome ?? null;
        $outcome = $rawOutcome !== null ? CallOutcome::tryFrom($rawOutcome) : null;
        if (!$outcome) {
            throw new BadRequest('Invalid call outcome: ' . $rawOutcome);
        }
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

        // Follow-up handling for call flows
        if ($outcome->value === CallOutcome::CALL_AGAIN->value || $outcome->value === CallOutcome::NO_ANSWER->value) {
            $this->ensureDefaultFollowUpIfCallAgain($leadId, $callAgainDateTime);
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

    /**
     * Log a kickstart session outcome and manage follow-up and note.
     * @throws BadRequest
     * @throws Exception
     */
    public function logKickstart(StdClass $data): array
    {
        $leadId = (string) $data->id;
        $rawOutcome = $data->outcome ?? null;
        $outcome = $rawOutcome !== null ? KickstartOutcome::tryFrom($rawOutcome) : null;
        if (!$outcome) {
            throw new BadRequest('Invalid kickstart outcome: ' . $rawOutcome);
        }
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

    /**
     * Log a follow-up outcome for a kickstart.
     * @throws BadRequest
     * @throws Exception
     */
    public function logKickstartFollowUp(StdClass $data): array
    {
        $leadId = (string) $data->id;
        $rawOutcome = $data->outcome ?? null;
        $outcome = $rawOutcome !== null ? KickstartOutcome::tryFrom($rawOutcome) : null;
        if (!$outcome) {
            throw new BadRequest('Invalid kickstart follow-up outcome: ' . $rawOutcome);
        }
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

    /**
     * Log that a message has been sent.
     * @throws Exception
     */
    public function logMessageSent(StdClass $data): array
    {
        $leadId = (string) $data->id;
        return $this->logEvent($leadId, LeadEventType::MESSAGE_SENT);
    }

    /**
     * Log the outcome after a message has been sent.
     * @throws BadRequest
     * @throws Exception
     */
    public function logMessageOutcome(StdClass $data): array
    {
        $leadId = (string) $data->id;
        $rawOutcome = $data->outcome ?? null;
        $outcome = $rawOutcome !== null ? MessageSentOutcome::tryFrom($rawOutcome) : null;
        if (!$outcome) {
            throw new BadRequest('Invalid message sent outcome: ' . $rawOutcome);
        }
        $callAgainDateTime = $data->callAgainDateTime ?? null;
        $coachNote = $data->coachNote ?? null;

        if (!isset(self::MESSAGE_OUTCOME_EVENT_MAP[$outcome->value])) {
            throw new BadRequest('Invalid message sent outcome: ' . $outcome->value);
        }

        $eventIds = [];
        foreach (self::MESSAGE_OUTCOME_EVENT_MAP[$outcome->value] as $eventType) {
            $eventIds[] = $this->logEvent($leadId, $eventType)['eventId'];
        }

        if ($outcome->value === MessageSentOutcome::CALL_AGAIN->value) {
            // Mirror phone flow: ensure follow-up (provided or default)
            $this->ensureDefaultFollowUpIfCallAgain($leadId, $callAgainDateTime);
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
    }

    private function fetchLead(string $leadId): ?Entity
    {
        return $this->entityManager->getEntity('Lead', $leadId);
    }

    /**
     * Ensures a follow-up exists when the lead is (or remains) in call_again.
     * - If a follow-up already exists, do nothing.
     * - If a date is provided, use it.
     * - Otherwise set a default: +1 day in Europe/Brussels.
     */
    /**
     * Ensure there is a follow-up when the lead is in call_again status.
     * @throws Exception
     */
    private function ensureDefaultFollowUpIfCallAgain(string $leadId, ?string $providedDateTime = null): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        $status = (string) ($lead->get('status') ?? '');
        if ($status !== 'call_again') {
            // Not in call_again (e.g., escalated to message_to_be_sent) → nothing to do
            return;
        }

        $existing = (string) ($lead->get('cFollowUpAction') ?? '');
        if ($existing !== '') {
            return; // Respect existing follow-up
        }

        $note = 'Opnieuw bellen';
        if ($providedDateTime) {
            $this->addFollowupAction($leadId, $providedDateTime, $note);
            return;
        }

        // Default: +1 day Europe/Brussels
        $tz = new DateTimeZone('Europe/Brussels');
        $dt = new DateTime('now', $tz);
        $dt->modify('+1 day');
        $this->addFollowupAction($leadId, $dt->format('Y-m-d H:i:s'), $note);
    }

    /**
     * Prepend a coach note to the lead notes with a timestamp and source.
     * @throws Exception
     */
    private function addCoachNote(string $leadId, string $coachNote, string $source, ?string $eventDate = null): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        $timezone = new DateTimeZone('Europe/Brussels');
        if (!$eventDate) {
            $dt = new DateTime('now', $timezone);
        } else {
            $dt = new DateTime($eventDate);
            $dt->setTimezone($timezone);
        }

        $existingNotes = (string) ($lead->get('cNotes') ?? '');

        $formattedHeader = $dt->format('[d/m/Y H:i]');
        $newLine = "$formattedHeader ($source): $coachNote";
        $updatedNotes = $existingNotes ? ($newLine . "\n\n" . $existingNotes) : $newLine;

        $lead->set('cNotes', $updatedNotes);
        $this->entityManager->saveEntity($lead);
    }

    /**
     * Set formatted follow-up action text on the lead.
     * @throws Exception
     */
    private function addFollowupAction(string $leadId, string $callAgainDateTime, string $followUpNote = 'Opnieuw bellen'): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        $dt = new DateTime($callAgainDateTime);
        $formatted = $dt->format('d/m/Y H:i');
        $line = "$followUpNote: $formatted";
        $lead->set('cFollowUpAction', $line);
        $this->entityManager->saveEntity($lead);
    }

    /**
     * Clear the follow-up action field.
     */
    private function clearFollowupAction(string $leadId): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }

        $lead->set('cFollowUpAction', '');
        $this->entityManager->saveEntity($lead);
    }

    /**
     * Increase call counter for the lead.
     */
    private function incrementCallCount(string $leadId): void
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return;
        }
        
        $currentCount = (int) ($lead->get('cCallCount') ?? 0);
        $newCount = $currentCount + 1;
        $lead->set('cCallCount', $newCount);
        $this->entityManager->saveEntity($lead);
    }

    /**
     * Update the lead status based on the event type, with special handling for NO_ANSWER.
     */
    private function updateLeadStatus(Entity $lead, LeadEventType $eventType): void
    {
        if ($eventType->value === LeadEventType::NO_ANSWER->value) {
            $team = $lead->get('cTeam');
            $maxCallAttempts = 3;
            if ($team instanceof Entity) {
                $teamMax = $team->get('maxCallAttempts');
                if ($teamMax !== null) {
                    $maxCallAttempts = (int) $teamMax;
                }
            }
            $currentCallCount = (int) ($lead->get('cCallCount') ?? 0);

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
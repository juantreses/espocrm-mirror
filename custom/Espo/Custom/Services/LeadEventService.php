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
use Espo\Custom\Services\LeadLifecycle\LeadStateFactory;
use Espo\Custom\Services\LeadLifecycle\LeadSideEffectService;

class LeadEventService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config,
        private readonly LeadStateFactory $stateFactory,
        private readonly LeadSideEffectService $sideEffectService,
    ) {}

    public function logCall(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            throw new NotFound('Lead not found');
        }
        
        $outcome = CallOutcome::from($data->outcome);
        $callAgainDateTime = $data->callAgainDateTime ?? null;
        $coachNote = $data->coachNote ?? null;

        // 1. Instantiate the current state
        $currentState = $this->stateFactory->create($lead->get('status'));

        // 2. Delegate the transition logic to the State object
        switch ($outcome->value) {
            case CallOutcome::INVITED->value:
                $currentState->inviteToKS($lead);
                break;

            case CallOutcome::CALL_AGAIN->value:
                $currentState->moveToCallAgain($lead);
                $this->sideEffectService->addFollowupAction($lead, $callAgainDateTime);
                break;
                
            case CallOutcome::NO_ANSWER->value:
                // Special check for Max Call Attempts (logic is now internal to the Service)
                $team = $lead->get('cTeam');
                $maxCallAttempts = $team->get('maxCallAttempts') ?? 3;
                $currentCallCount = $lead->get('cCallCount') ?? 0;
                
                if ($currentCallCount >= $maxCallAttempts) {
                    $currentState->moveToMessageQueue($lead);
                } else {
                    $currentState->moveToCallAgain($lead);
                }
                $this->sideEffectService->clearFollowupAction($leadId); // No immediate follow-up
                break;

            case CallOutcome::WRONG_NUMBER->value:
                $currentState->kill($lead, 'Wrong number');
                $this->sideEffectService->clearFollowupAction($lead);
                break;

            case CallOutcome::NOT_INTERESTED->value:
                $currentState->disqualify($lead, 'Not interested');
                $this->sideEffectService->clearFollowupAction($lead);
                break;
                
            default:
                throw new BadRequest('Invalid call outcome: ' . $outcome->value);
        }

        // 3. Save the Lead (status and side effects like cCallCount are updated by State/SideEffectService)
        $this->entityManager->saveEntity($lead);

        if ($coachNote) {
            $this->addCoachNote($leadId, $coachNote, 'Telefoon', $data->callDateTime ?? null);
        }

        // Return the updated status from the Lead entity
        return [
            'success' => true,
            'leadStatus' => $lead->get('status'),
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

        $timezone = new \DateTimeZone('Europe/Brussels');
        if (!$eventDate) {
            $dt = new \DateTime('now', $timezone);
        } else {
            $dt = new \DateTime($eventDate);
            $dt->setTimezone($timezone);
        }

        $existingNotes = (string) ($lead->get('cNotes') ?? '');

        $formattedHeader = $dt->format('[d/m/Y H:i]');
        $newLine = "$formattedHeader ($source): $coachNote";
        $updatedNotes = $existingNotes ? ($newLine . "\n\n" . $existingNotes) : $newLine;

        $lead->set('cNotes', $updatedNotes);
        $this->entityManager->saveEntity($lead);
    }
}
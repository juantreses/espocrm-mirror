<?php
declare(strict_types=1);

namespace Espo\Custom\Services\LeadLifecycle;

use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\EntityManager;
use DateTime;
use DateTimeZone;

class LeadSideEffectService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    public function incrementCallCount(Lead $lead): void
    {
        $currentCount = $lead->get('cCallCount') ?? 0;
        $newCount = $currentCount + 1;
        $lead->set('cCallCount', $newCount);
        // Do NOT save the lead here. The calling State method saves the lead with the status change.
    }

    public function addFollowupAction(Lead $lead, string $callAgainDateTime, string $followUpNote = 'Opnieuw bellen'): void
    {
        $dt = new DateTime($callAgainDateTime);
        $formatted = $dt->format('d/m/Y H:i');
        $line = "$followUpNote: $formatted";
        $lead->set('cFollowUpAction', $line);
    }

    public function clearFollowupAction(Lead $lead): void
    {
        $lead->set('cFollowUpAction', '');
    }

    public function logEvent(Lead $lead, string $eventType, ?string $note = null, ?string $eventDate = null): void
    {
        // NOTE: This assumes eventDate is current time. If not, pass date as argument.
        $eventRepository = $this->entityManager->getRepository('CLeadEvent');
        $event = $this->entityManager->getEntity('CLeadEvent');

        $timezone = new \DateTimeZone('UTC');
        if (!$eventDate) {
            $dt = new \DateTime('now', $timezone);
        } else {
            $dt = new \DateTime($eventDate);
            $dt->setTimeZone($timezone);
        }
        $event->set([
            'eventType' => $eventType->value,
            'eventDate' => $dt->format('Y-m-d H:i:s'),
        ]);

        $event->set([
            'eventType' => $eventType,
            'eventDate' => $dt->format('Y-m-d H:i:s'),
            'note' => $note,
        ]);
        
        $this->entityManager->saveEntity($event);
        $eventRepository->getRelation($event, 'lead')->relate($lead);
        $this->entityManager->saveEntity($event);
    }
}

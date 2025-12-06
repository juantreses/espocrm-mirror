<?php
declare(strict_types=1);

namespace Espo\Custom\Services\LeadLifecycle\State;

use Espo\Core\Entities\Lead;
use Espo\Custom\Enums\LeadEventType;

class AssignedLeadState extends BaseLeadState
{
    // Assigned -> Call_Again (No answer)
    public function moveToCallAgain(Lead $lead): void
    {
        // Side effect: Increment call counter
        $this->sideEffectService->incrementCallCount($lead);

        $lead->set('status', 'call_again');
        $this->logEvent($lead, LeadEventType::NO_ANSWER->value, 'Geen antwoord.');
    }

    // Assigned -> Invited
    public function invite(Lead $lead): void
    {
        $lead->set('status', 'invited');
        $this->logEvent($lead, LeadEventType::INVITED->value, 'Lead uitgenodigd voor Kickstart/IOM.');
    }

    // Assigned -> Disqualified (Not interested)
    public function disqualify(Lead $lead, string $reason): void
    {
        $lead->set('status', 'disqualified');
        $this->logEvent($lead, LeadEventType::NOT_INTERESTED->value, 'Lead vervallen: ' . $reason);
    }
    
    // Assigned -> Dead (Wrong number)
    public function kill(Lead $lead, string $reason): void
    {
        $lead->set('status', 'Dead');
        $this->logEvent($lead, LeadEventType::WRONG_NUMBER->value, 'Lead marked Dead: ' . $reason);
    }
}
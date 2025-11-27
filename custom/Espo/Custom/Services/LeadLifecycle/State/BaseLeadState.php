<?php
declare(strict_types=1);

namespace Espo\Custom\Services\LeadLifecycle\State;

use Espo\Modules\Crm\Entities\Lead;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\EntityManager;

abstract class BaseLeadState implements LeadStateInterface
{
    public function __construct(
        protected EntityManager $entityManager
    ) {}

    // --- DEFAULT FORBIDDEN IMPLEMENTATIONS ---
    // If a transition is called on a State class that doesn't override it, 
    // it will throw a Forbidden exception, enforcing the state machine rule.

    public function assign(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function moveToCallAgain(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function moveToMessageQueue(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function markMessageSent(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function inviteToKS(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function bookAppointment(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function handleCancellation(Lead $lead, string $action): void { $this->throwForbidden(__FUNCTION__); }
    public function moveToStillThinking(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function startProgram(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function convert(Lead $lead): void { $this->throwForbidden(__FUNCTION__); }
    public function disqualify(Lead $lead, string $reason): void { $this->throwForbidden(__FUNCTION__); }
    public function resurrect(Lead $lead, string $targetStatus): void { $this->throwForbidden(__FUNCTION__); }

    // --- HELPER METHOD ---
    protected function throwForbidden(string $action): void
    {
        // Use reflection to get the current state class name for better logging
        $stateClass = (new \ReflectionClass($this))->getShortName();
        throw new Forbidden("Action '{$action}' is not allowed from state '{$stateClass}'.");
    }

    
}
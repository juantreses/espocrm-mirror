<?php
declare(strict_types=1);

namespace Espo\Custom\Services\LeadLifecycle\State;

use Espo\Core\Entities\Lead;
use Espo\Custom\Enums\LeadEventType;

class NewLeadState extends BaseLeadState
{
    // New -> Assigned (Team assigned)
    public function assign(Lead $lead): void
    {
        $GLOBALS['log']->info("Setting status to assigned");
        $lead->set('status', 'assigned');
        // NOTE: The Team assignment logic itself remains in LeadService::processLead
        $this->logEvent($lead, LeadEventType::ASSIGNED->value, 'Lead toegewezen aan team.');
    }
}
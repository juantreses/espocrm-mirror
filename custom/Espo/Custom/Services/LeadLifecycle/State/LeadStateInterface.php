<?php
declare(strict_types=1);

namespace Espo\Custom\Services\LeadLifecycle\State;

use Espo\Core\Entities\Lead;
use Espo\Core\Exceptions\Forbidden;

interface LeadStateInterface
{
    public function assign(Lead $lead): void;
    public function moveToCallAgain(Lead $lead): void;
    public function moveToMessageQueue(Lead $lead): void;
    public function markMessageSent(Lead $lead): void;
    public function invite(Lead $lead): void;
    public function bookAppointment(Lead $lead): void;
    public function handleCancellation(Lead $lead, string $action): void;
    public function moveToStillThinking(Lead $lead): void;
    public function startProgram(Lead $lead): void;
    public function convert(Lead $lead): void;
    public function disqualify(Lead $lead, string $reason): void;
    public function kill(Lead $lead, string $reason): void;
    public function resurrect(Lead $lead, string $targetStatus): void;
}
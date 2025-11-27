<?php

namespace Espo\Custom\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\CallOutcome;

class LogCallValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        $outcome = $data->outcome ?? null;
        if (!$outcome || !CallOutcome::isValid($outcome)) {
            throw new BadRequest('Invalid call outcome.');
        }

        if (!empty($data->callDateTime)) {
            $callDate = new \DateTime($data->callDateTime);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
    
            if ($calldate > $now) {
                throw new BadRequest('Gesprek datum/tijd mag niet in de toekomst zijn.');
            }
        }
    
        if ($outcome === CallOutcome::CALL_AGAIN->value) {
            if (empty($data->callAgainDateTime)) {
                throw new BadRequest('Datum/tijd opnieuw bellen is verplicht.');
            }
    
            $callAgain = new \DateTime($data->callAgainDateTime);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
    
            if ($callAgain <= $now) {
                throw new BadRequest('Datum/tijd opnieuw bellen moet in de toekomst zijn.');
            }
        }
    }
}
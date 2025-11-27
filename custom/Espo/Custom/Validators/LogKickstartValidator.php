<?php

namespace Espo\Custom\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\KickstartOutcome;

class LogKickstartValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        $outcome = $data->outcome ?? null;
        if (!$outcome || !KickstartOutcome::isValid($outcome)) {
            throw new BadRequest('Invalid call outcome.');
        }

        if (!empty($data->kickstartDateTime)) {
            $kickstart = new \DateTime($data->kickstartDateTime);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
    
            if ($kickstart > $now) {
                throw new BadRequest('Kickstartdatum/tijd mag niet in de toekomst zijn.');
            }
        }
    
        if ($outcome === KickstartOutcome::STILL_THINKING->value) {
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
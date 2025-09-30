<?php

namespace Espo\Modules\Vanko\Hooks\Lead;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;

class VankoBeforeSaveLead implements BeforeSave
{
    public function __construct(
        private readonly Log $log,
    ) {}

    public function beforeSave(Entity $lead, SaveOptions $options): void
    {
        $this->log->info('Vanko before save triggered.');
    }
}
<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\VankoWebhookService;
use Espo\Custom\Services\EntitySyncService;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly VankoWebhookService $vankoWebhookService,
        private readonly EntitySyncService $entitySyncService,
    ) {}

    public function afterSave(Entity $lead, SaveOptions $options): void
    {
        if ($lead->get('suppressVankoSync')) {
            return;
        }

        try {
            $this->log->info('Lead After Save Hook triggered for Lead ID: ' . $lead->getId());
            $this->vankoWebhookService->processVankoLead($lead);
            $this->entitySyncService->syncFromLead($lead);
        } catch (\Exception $e) {
            $this->log->error('Lead After Save Hook error: ' . $e->getMessage());
        }
    }
}
<?php

namespace Espo\Custom\Hooks\Contact;

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

    public function afterSave(Entity $contact, SaveOptions $options): void
    {
        if ($contact->get('suppressVankoSync')) {
            return;
        }

        try {
            $this->log->info('Contact After Save Hook triggered for Contact ID: ' . $contact->getId());
            $this->vankoWebhookService->processVankoContact($contact);
            $this->entitySyncService->syncFromContact($contact);
        } catch (\Exception $e) {
            $this->log->error('Contact After Save Hook error: ' . $e->getMessage());
        }
    }
}
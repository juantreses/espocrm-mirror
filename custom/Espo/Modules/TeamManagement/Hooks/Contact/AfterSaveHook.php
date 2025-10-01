<?php

namespace Espo\Custom\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\VankoWebhookService;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly VankoWebhookService $vankoWebhookService,
    ) {}

    public function afterSave(Entity $contact, SaveOptions $options): void
    {
        try {
            $this->log->info('Contact After Save Hook triggered for Contact ID: ' . $contact->getId());
           
            if ($this->vankoWebhookService->hasVankoFieldsChanged($contact)) {
                $this->vankoWebhookService->syncAndProcessFromContact($contact);
            }
        } catch (\Exception $e) {
            $this->log->error('Contact After Save Hook error: ' . $e->getMessage());
        }
    }
}
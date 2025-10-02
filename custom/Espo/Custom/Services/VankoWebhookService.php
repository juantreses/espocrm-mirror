<?php

declare(strict_types=1);

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Core\Utils\Config;
use Espo\Custom\Traits\WebhookTrait;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Service for handling Vanko webhook operations.
 */
class VankoWebhookService
{
    use WebhookTrait;

    private const VANKO_FIELDS_TO_WATCH = [
        'cVankoCRM',
        'firstName',
        'lastName',
        'emailAddress',
        'phoneNumber',
        'cDateOfBirth',
        'cTeam',
        'cTypeKlant',
        'cTeamId',
        'cSlimFitCenterId'
    ];
    
    private const CLIENT_TYPE_MAPPING = [
        'sample_pack' => 'Proefpakket',
        'sport_only' => 'SportOnly',
        '21_day_bootcamp' => '21 Dagen Bootcamp',
        'retail' => 'Retail',
        'pcx_15' => 'PCX15',
        'pcx_25' => 'PCX25',
        'pcx_35' => 'PCX35',
        'distributor' => 'Distributeur',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Log $log,
        private readonly EntityManager $entityManager,
    ) {}


    public function hasVankoFieldsChanged(Entity $entity): bool
    {
        foreach (self::VANKO_FIELDS_TO_WATCH as $field) {
            if ($entity->isAttributeChanged($field)) {
                $this->log->info('Vanko field changed detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId() . ' - field: ' . $field);
                return true;
            }
        }
        return false;
    }

    public function syncAndProcessFromContact(Entity $contact): void
    {
        $this->log->info('Syncing and processing webhook from Contact ID: ' . $contact->getId());
        $lead = $this->getRelatedLead($contact);

        if ($lead) {
            $this->log->info('Related Lead found, updating fields on Lead entity.');
            $lead->set('firstName', $contact->get('firstName'));
            $lead->set('lastName', $contact->get('lastName'));
            $lead->set('emailAddress', $contact->get('emailAddress'));
            $lead->set('phoneNumber', $contact->get('phoneNumber'));
            $lead->set('cDateOfBirth', $contact->get('cDateOfBirth'));
            $lead->set('cTeam', $contact->get('cTeam'));

            $this->entityManager->saveEntity(
                $lead, 
                [
                    SaveOption::SKIP_ALL => true,
                ]
            );
        }
        
        $this->processVankoWebhook($lead, $contact);
    }

    public function syncAndProcessFromLead(Entity $lead): void
    {
        $this->log->info('Syncing and processing webhook from Lead ID: ' . $lead->getId());
        $contact = $this->getRelatedContact($lead);

        if ($contact) {
            $this->log->info('Related Contact found, updating fields on Contact entity.');
            $contact->set('firstName', $lead->get('firstName'));
            $contact->set('lastName', $lead->get('lastName'));
            $contact->set('emailAddress', $lead->get('emailAddress'));
            $contact->set('phoneNumber', $lead->get('phoneNumber'));
            $contact->set('cDateOfBirth', $lead->get('cDateOfBirth'));
            $contact->set('cTeam', $lead->get('cTeam'));

            $this->entityManager->saveEntity(
                $contact, 
                [
                    SaveOption::SKIP_ALL => true,
                ]
            );
        }

        $this->processVankoWebhook($lead, $contact);
    }

    private function processVankoWebhook(Entity $lead, ?Entity $contact): void
    {
        $this->log->info('Preparing to send webhook from Lead ID: ' . $lead->getId());
        $this->sendVankoWebhook($this->buildWebhookData($lead, $contact));
    }
    
    private function getRelatedLead(Entity $contact): ?Entity
    {
        $vankoCRMId = $contact->get('cVankoCRM');
        if (!$vankoCRMId) {
            return null;
        }

        return $this->entityManager->getRepository('Lead')->where(['cVankoCRM' => $vankoCRMId])->findOne();
    }

    private function getRelatedContact(Entity $lead): ?Entity
    {
        $vankoCRMId = $lead->get('cVankoCRM');
        if (!$vankoCRMId) {
            return null;
        }

        return $this->entityManager->getRepository('Contact')->where(['cVankoCRM' => $vankoCRMId])->findOne();
    }


    private function buildWebhookData(Entity $lead, ?Entity $contact = null): array
    {
        return [
            'id' => $lead->getId(),
            'contact_id' => $contact ? $contact->getId() : null,
            'vanko_id' => $this->getFieldValue($contact, $lead, 'cVankoCRM'),
            'first_name' => $this->getFieldValue($contact, $lead, 'firstName'),
            'last_name' => $this->getFieldValue($contact, $lead, 'lastName'),
            'emailAddress' => $this->getFieldValue($contact, $lead, 'emailAddress'),
            'phoneNumber' => $this->getFieldValue($contact, $lead, 'phoneNumber'),
            'date_of_birth' => $this->getFieldValue($contact, $lead, 'cDateOfBirth'),
            'team' => $this->getTeamName($contact, $lead),
            'client_type' => $this->mapClientType($contact),
        ];
    }

    private function getFieldValue(?Entity $contact, Entity $lead, string $field): mixed
    {
        return $contact && $contact->get($field) !== null ? $contact->get($field) : $lead->get($field);
    }

    private function getTeamName(?Entity $contact, Entity $lead): ?string
    {
        $team = $this->getFieldValue($contact, $lead, 'cTeam');
        return $team ? $team->get('name') : null;
    }

    private function mapClientType(?Entity $contact = null): ?string
    {
        if ($contact && $contact->get('cTypeKlant')) {
            $clientType = $contact->get('cTypeKlant');
        } 
        else {
            return 'Lead';
        }

        return self::CLIENT_TYPE_MAPPING[$clientType] ?? 'Lead';
    }

    private function sendVankoWebhook(array $webhookData): void
    {
        $endpoint = $this->config->get('vanko.webhooks.lead.process');

        if (!$endpoint) {
            $this->log->warning('Vanko webhook endpoint not configured');
            return;
        }

        $this->log->info('Sending webhook to Vanko for Lead ID: ' . $webhookData['id']);
        $this->sendWebhookSync(
            endpoint: $endpoint,
            payload: $webhookData,
            serviceName: 'Vanko',
            method: 'POST',
            timeout: 30
        );
    }

    // Required by WebhookTrait
    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    protected function getLog(): Log
    {
        return $this->log;
    }
}
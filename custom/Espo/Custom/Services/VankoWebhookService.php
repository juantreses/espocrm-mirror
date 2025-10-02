<?php

declare(strict_types=1);

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Core\Utils\Config;
use Espo\Custom\Traits\WebhookTrait;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Service for handling Vanko webhook operations.
 */
class VankoWebhookService
{
    use WebhookTrait;

    private const BASE_FIELDS_TO_WATCH = [
        'cVankoCRM',
        'firstName',
        'lastName',
        'emailAddress',
        'phoneNumber',
        'cTeamId',
        'cSlimFitCenterId'
    ];
    private const LEAD_FIELDS_TO_WATCH = [
        "status",
    ];
    private const CONTACT_FIELDS_TO_WATCH = [
        'cDateOfBirth',
        'cTypeKlant',
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
        'spark_trail' => 'Spark Proefperiode',
        'spark_subscription' => 'Spark Subscription',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Log $log,
        private readonly EntityManager $entityManager,
    ) {}

    public function processVankoLead(Entity $entity): void
    {
        if ($this->hasLeadFieldsChanged($entity)) {
            $this->processVankoLeadWebhook($entity);
        }
    }

    public function processVankoContact(Entity $entity): void
    {
        if ($this->hasContactFieldsChanged($entity)) {
            $this->processVankoContactWebhook($entity);
        }
    }

    public function hasLeadFieldsChanged(Entity $entity): bool
    {
        $changed = false;
        $changed = $this->hasFieldsChanged($entity,self::BASE_FIELDS_TO_WATCH);
        if($changed){
            $this->log->info('Base field change detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId());
            return $changed;
        }
        $changed = $this->hasFieldsChanged($entity,self::LEAD_FIELDS_TO_WATCH);
        if($changed){
            $this->log->info('Lead field change detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId());
            return $changed;
        }
        return $changed;
    }

    public function hasContactFieldsChanged(Entity $entity): bool
    {
        $changed = false;
        $changed = $this->hasFieldsChanged($entity,self::BASE_FIELDS_TO_WATCH);
        if($changed){
            $this->log->info('Base field change detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId());
            return $changed;
        }
        $changed = $this->hasFieldsChanged($entity,self::CONTACT_FIELDS_TO_WATCH);
        if($changed){
            $this->log->info('Lead field change detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId());
            return $changed;
        }
        return $changed;
    }

    public function hasFieldsChanged(Entity $entity, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($entity->isAttributeChanged($field)) {
                $this->log->info('Field change detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId() . ' - field: ' . $field);
                return true;
            }
        }
        return false;
    }

    public function hasVankoFieldsChanged(Entity $entity): bool
    {
        foreach (self::BASE_FIELDS_TO_WATCH as $field) {
            if ($entity->isAttributeChanged($field)) {
                $this->log->info('Vanko field changed detected for ' . $entity->getEntityType() . ' ID: ' . $entity->getId() . ' - field: ' . $field);
                return true;
            }
        }
        return false;
    }

    private function processVankoLeadWebhook(Entity $entity): void
    {
        $this->log->info('Preparing to send webhook from Lead ID: ' . $entity->getId());
        $this->sendVankoLeadWebhook($this->buildWebhookLeadData($entity));
    }
    private function processVankoContactWebhook(Entity $entity): void
    {
        $this->log->info('Preparing to send webhook from Contact ID: ' . $entity->getId());
        $this->sendVankoContactWebhook($this->buildWebhookContactData($entity));
    }

    private function buildWebhookLeadData(Entity $entity) : array 
    {
        $data = $this->buildWebhookBaseData($entity);
        $data['status'] = $entity->get('status');
        return $data;
    }

    private function buildWebhookContactData(Entity $entity) : array 
    {
        $data = $this->buildWebhookBaseData($entity);
        $data['date_of_birth'] = $entity->get('cDateOfBirth');
        $data['client_type'] = self::CLIENT_TYPE_MAPPING[$entity->get('cTypeKlant')]?self::CLIENT_TYPE_MAPPING[$entity->get('cTypeKlant')]:"Lead";
        return $data;
    }

    private function buildWebhookBaseData(Entity $entity): array
    {
        $data = [];
        $data['id'] = $entity->getId();
        $data['vanko_id'] = $entity->get('cVankoCRM');
        $data['first_name'] = $entity->get('firstName');
        $data['last_name'] = $entity->get('lastName');
        $data['emailAddress'] = $entity->get('emailAddress');
        $data['phoneNumber'] = $entity->get('phoneNumber');
        $data['team'] = $entity->get('cTeam') ? $entity->get('cTeam')->get('name') : "";
        $data['slimfitcenter'] = $entity->get('cSlimFitCenter') ? $entity->get('cSlimFitCenter')->get('name') : "";
        return $data;
    }

    private function sendVankoLeadWebhook(array $webhookData): void
    {
        $this->log->info('Sending webhook to Vanko for Lead ID: ' . $webhookData['id']);
        $endpoint = $this->config->get('vanko.webhooks.lead.process');
        $this->sendVankoWebhook($webhookData,$endpoint);
    }

    private function sendVankoContactWebhook(array $webhookData): void
    {
        $this->log->info('Sending webhook to Vanko for Lead ID: ' . $webhookData['id']);
        $endpoint = $this->config->get('vanko.webhooks.contact.process');
        $this->sendVankoWebhook($webhookData,$endpoint);
    }

    private function sendVankoWebhook(array $webhookData, string $endpoint): void
    {
        if (!$endpoint) {
            $this->log->warning('Vanko webhook endpoint not configured');
            return;
        }
        $this->log->info('Sending webhook to Vanko for ID: ' . $webhookData['id']);
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
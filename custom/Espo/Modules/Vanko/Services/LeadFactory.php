<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Core\Utils\Log;
use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Creates and updates Lead entities from API data.
 */
class LeadFactory
{
    /**
     * Unified field mapping structure that supports both simple and fallback mappings.
     * For simple mappings, use a single-element array.
     * For fallback mappings, list source fields in priority order.
     * The first valid source field found will be used.
     */
    private const FIELD_MAPPING = [
        // Simple field mappings (single source field)
        'cVankoCRM' => ['contact_id'],
        'firstName' => ['first_name'],
        'lastName' => ['last_name'],
        'emailAddress' => ['email'],
        'phoneNumber' => ['phone'],
        'cDateOfBirth' => ['date_of_birth'],
        
        // Fallback field mappings (multiple source fields in priority order)
        'cExternalCreatedAt' => [
            'CC_SlimFitCenter_Campagne_Registratie', // Primary field
            'date_created',                         // Backup field
        ],

        // Question Mapping
        'cVraag01' => ['Ik selecteer per week 10 mensen voor één van onze gratis en vrijblijvende ervaringen in ons SFC. Welke zou jij het liefste volgen?']

    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
    ) {}

    /**
     * Creates a new Lead entity instance from the provided data.
     */
    public function createFromData(object $data): ?Lead
    {
        $this->log->info("Creating new lead for Vanko ID {$data->contact_id}");
        try {
            /** @var Lead $lead */
            $lead = $this->entityManager->getEntity('Lead');
            $this->applyDataToLead($lead, $data);
            
            $this->entityManager->saveEntity(
                $lead, 
                [
                    'skipAfterSave' => true,
                    SaveOption::KEEP_NEW => true,
                    SaveOption::KEEP_DIRTY => true,
                ]
            );

            $this->log->info("Successfully instantiated lead {$lead->getId()} for Vanko ID {$data->contact_id}");
            return $lead;
        } catch (\Exception $e) {
            $this->log->error("Failed to create lead for Vanko ID {$data->contact_id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Applies updates to an existing Lead entity from provided data.
     * Returns true if any fields were changed.
     */
    public function updateFromData(Lead $lead, object $data): Lead
    {
        $this->log->info("Preparing update for lead {$lead->getId()} from Vanko ID {$data->contact_id}");
        $updateData = [];

        foreach (self::FIELD_MAPPING as $espoField => $sourceFields) {
            foreach ($sourceFields as $vankoField) {
                if (property_exists($data, $vankoField)) {
                    $newValue = trim((string) $data->$vankoField);
                    if ($newValue !== '' && $lead->get($espoField) !== $newValue) {
                        $updateData[$espoField] = $newValue;
                    }
                    break;
                }
            }
        }

        if (empty($updateData)) {
            $this->log->info("No field changes detected for lead {$lead->getId()}");
            return $lead;
        }

        $this->log->info("Applying updates to lead {$lead->getId()}: " . json_encode(array_keys($updateData)));
        $lead->set($updateData);
        return $lead;
    }

    private function applyDataToLead(Lead $lead, object $data): void
    {
        foreach (self::FIELD_MAPPING as $espoField => $sourceFields) {
            foreach ($sourceFields as $vankoField) {
                if (isset($data->$vankoField) && trim((string) $data->$vankoField) !== '') {
                    if(is_array($data->$vankoField)){
                        $vankodata = implode("\n", $data->$vankoField);
                    }else{
                        $vankodata = (string)$data->$vankoField;
                    }
                    $lead->set($espoField, trim($vankodata));
                    break;
                }
            }
        }
    }
}
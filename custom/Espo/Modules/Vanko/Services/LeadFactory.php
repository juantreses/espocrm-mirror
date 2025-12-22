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

    ];

    /**
     * Keys must match EXACTLY what is in the incoming JSON data,
     * Pay close attention to exact characters (like the en-dash '–' or non-breaking space ' ').
     */
    private const SURVEY_MAPPING = [
        'Wat had je vanmorgen als ontbijt?',
        'Hoe zou je je algemene gezondheid op dit moment beoordelen? (Schaal 1–10)',
        'Ik geef je 5 gezondheidsresultaten. Stel dat er één vanaf nu zou werken, welke zou je kiezen?',
        'Wil je vrijblijvend meer info over hoe je een centje kan bijvrienden als welzijnscoach? ',
        'Welke sport oefen je uit?',
        'Ik selecteer per week 10 mensen voor één van onze gratis en vrijblijvende ervaringen in ons SFC. Welke zou jij het liefste volgen?',
        'Opmerking',
        'Welke van deze doelen spreken jou het meest aan?',
        'Slaap je gemiddeld voldoende (7–9 uur per nacht)?',
        'Doe je aan sport?',
        'Hoeveel uur in de week sport je?',
        'Waar wil jij je huid het liefst in verbeteren?',
        'Kun je de komende weken op zondagochtend tussen 10.00 en 12.00 meedoen aan de gratis workshop?',
        'Zou je de komende weken willen meedoen met een groepsles, als het voor jou uitkomt?',
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

            // Apply survey data as a JSON string
            $this->applySurveyData($lead, $data);

            // Set a runtime flag to stop the AfterSaveHook from syncing to Vanko just yet
            $lead->set('suppressVankoSync', true);
            
            $this->entityManager->saveEntity(
                $lead
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

        $currentSurveyJson = $lead->get('cSurveyData') ?? '';
        $newSurveyData = $this->extractSurveyData($data);
        $newSurveyJson = empty((array)$newSurveyData) 
        ? '' 
        : json_encode($newSurveyData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($currentSurveyJson !== $newSurveyJson) {
            if ($newSurveyJson !== '') {
                $lead->set('cSurveyData', $newSurveyJson);
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

                    $value = trim((string) $data->$vankoField);

                    if ($espoField === 'cExternalCreatedAt') {
                        try {
                            $dt = new \DateTime($value);
                            $dt->setTimezone(new \DateTimeZone('UTC')); 
                            $value = $dt->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            $this->log->warning("Failed to parse date for field $espoField: $value");
                        }
                    }

                    $lead->set($espoField, $value);
                    break;
                }
            }
        }
    }

    private function extractSurveyData(object $data): array
    {
        $surveyData = [];

        foreach (self::SURVEY_MAPPING as $questionKey) {
            if (property_exists($data, $questionKey)) {
                $answer = $data->$questionKey;
                
                if ($answer === '' || $answer === null) {
                    continue;
                }

                if (is_array($answer)) {
                    $surveyData[$questionKey] = implode(', ', $answer);
                } else {
                    $surveyData[$questionKey] = trim((string)$answer);
                }
            }
        }

        return $surveyData;
    }

    private function applySurveyData(Lead $lead, object $data): void
    {
        $surveyData = $this->extractSurveyData($data);
        if (!empty($surveyData)) {
            // Encode as a pretty-printed string for the TEXT field
            $jsonString = json_encode($surveyData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $lead->set('cSurveyData', $jsonString);
        }
    }
}
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

    // Pay close attention to exact characters (like the en-dash '–' or non-breaking space ' ').
    private const Q_HEALTH_SCORE = 'Hoe zou je je algemene gezondheid op dit moment beoordelen? (Schaal 1–10)';
    private const Q_SPORT_TYPE = 'Welke sport oefen je uit?';
    private const Q_FREE_EXPERIENCE = 'Ik selecteer per week 10 mensen voor één van onze gratis en vrijblijvende ervaringen in ons SFC. Welke zou jij het liefste volgen?';
    private const Q_BREAKFAST = 'Wat had je vanmorgen als ontbijt?';
    private const Q_5_RESULTS = 'Ik geef je 5 gezondheidsresultaten. Stel dat er één vanaf nu zou werken, welke zou je kiezen?';
    private const Q_COACH_INFO = 'Wil je vrijblijvend meer info over hoe je een centje kan bijvrienden als welzijnscoach? ';
    private const Q_GOALS = 'Welke van deze doelen spreken jou het meest aan?';
    private const Q_SLEEP = 'Slaap je gemiddeld voldoende (7–9 uur per nacht)?';
    private const Q_DO_SPORT = 'Doe je aan sport?';
    private const Q_SPORT_HOURS = 'Hoeveel uur in de week sport je?';
    private const Q_REMARK = 'Opmerking';

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
     * Mapping of SlimFit Centers to their specific survey questions.
     * Keys must match EXACTLY what is in the incoming JSON data, 
     * including exact spacing and special characters (like non-breaking spaces).
     */
    private const SURVEY_MAPPING = [
        'Sint-Katelijne-Waver' => [
            self::Q_BREAKFAST,
            self::Q_HEALTH_SCORE,
            self::Q_5_RESULTS,
            self::Q_COACH_INFO,
            self::Q_SPORT_TYPE,
            self::Q_FREE_EXPERIENCE,
            self::Q_REMARK,
        ],
        'Drongen' => [
            self::Q_HEALTH_SCORE,
            self::Q_GOALS,
            self::Q_SLEEP,
            self::Q_DO_SPORT,
            self::Q_SPORT_HOURS,
            self::Q_SPORT_TYPE,
            self::Q_FREE_EXPERIENCE,
            self::Q_REMARK,
        ],
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
                    $lead->set($espoField, trim((string) $data->$vankoField));
                    break;
                }
            }
        }
    }

    private function extractSurveyData(object $data): object
    {
        $surveyData = [];
        $center = $data->CC_SlimFitCenter ?? 'default';
        $questionsToExtract = self::SURVEY_MAPPING[$center] ?? self::SURVEY_MAPPING['default'];

        foreach ($questionsToExtract as $questionKey) {
            if (property_exists($data, $questionKey)) {
                 // Use the exact question key from mapping so we don't rely on varying input keys
                $answer = $data->$questionKey;
                if (is_array($answer)) {
                    $surveyData[$questionKey] = implode(', ', $answer);
                } else {
                    $surveyData[$questionKey] = trim((string)$answer);
                }
            }
        }

        return (object) $surveyData;
    }

    private function applySurveyData(Lead $lead, object $data): void
    {
        $surveyData = $this->extractSurveyData($data);
        if (!empty((array)$surveyData)) {
            // Encode as a pretty-printed string for the TEXT field
            $jsonString = json_encode($surveyData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $lead->set('cSurveyData', $jsonString);
        }
    }
}
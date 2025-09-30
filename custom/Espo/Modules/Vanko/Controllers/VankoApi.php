<?php

namespace Espo\Modules\Vanko\Controllers;

use Espo\Core\Controllers\RecordBase;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\Crm\Entities\Lead;

class VankoApi extends RecordBase
{
    public function postActionLead($params, $data)
    {
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }
            // Validate required fields
            $required = ['contact_id', 'first_name', 'phone'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data->$field)) {
                    $missing[] = $field;
                }
            }
            if (count($missing) > 0) {
                throw new BadRequest("Missing required field(s): " . implode(', ', $missing));
            }

            $service = $this->getServiceFactory()->create('LeadService');
            $result = $service->processLead($data);

            return $result;
        } catch (BadRequest $e) {
            // Return proper error response
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (\Exception $e) {
            // Log unexpected errors
            $GLOBALS['log']->error('Vanko API Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    public function postActionKickstart($params, $data)
    {
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }

            $lead = $this->findLeadByData($data);

            if (!$lead) {
                throw new BadRequest("Could not find a matching lead with the provided identifiers.");
            }

            $service = $this->getServiceFactory()->create('LeadEventService');
            return $service->logEvent($lead->getId(), LeadEventType::APPOINTMENT_BOOKED);

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400,
            ];
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Vanko API Kickstart Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ];
        }
    }

    public function postActionCancelKickstart($params, $data)
    {
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }

            $lead = $this->findLeadByData($data);

            if (!$lead) {
                throw new BadRequest("Could not find a matching lead with the provided identifiers.");
            }

            $service = $this->getServiceFactory()->create('LeadEventService');
            return $service->logEvent($lead->getId(), LeadEventType::APPOINTMENT_CANCELLED);

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400,
            ];
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Vanko API CancelKickstart Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ];
        }
    }

    public function postActionSyncState($params, $data)
    {
        try {
            if (!$data) {
                throw new BadRequest('No data provided');
            }
            
            $lead = $this->findLeadByData($data);

            if (!$lead) {
                throw new BadRequest("Could not find a matching lead with the provided identifiers.");
            }

            if (!in_array($lead->get('status'), ['new', 'assigned'])) {
                return;
            }

            $entityManager = $this->getEntityManager();

            if ($data->CC_SlimFitCenter_GebeldGeenIntresse) {
                $lead->set('status', 'not_interested');
                $entityManager->saveEntity($lead);
                return [
                    'success' => true,
                    'message' => 'Lead status synchronized successfully.'
                ];
            }

            if ($data->CC_SlimFitCenter_AfspraakIngeplanned === 'Ja') {
                $lead->set('status', 'appointment_booked');
                
            }

            if ($data->CC_SlimFitCenter_AanwezigheidsCheck === 'Ja' || $data->CC_SlimFitCenter_AanwezigheidsCheckattended === 'JA') {
                $lead->set('status', 'attended');
            }

            if ($data->CC_SlimFitCenter_AanwezigheidsCheck === 'Nee' || $data->CC_SlimFitCenter_AanwezigheidsCheckattended === 'NEE') {
                $lead->set('status', 'no_show');
            }

            if (($data->CC_SlimFitCenter_AanwezigheidsCheck === 'Ja' || $data->CC_SlimFitCenter_AanwezigheidsCheckattended === 'JA') && $data->CC_SlimFitCenter_KlantType === 'Lead') {
                $lead->set('status', 'not_converted');
            }

            if ($data->CC_SlimFitCenter_KlantType !== 'Lead') {
                $lead->set('status', 'converted');
            }

            $entityManager->saveEntity($lead);

            return [
                'success' => true,
                'message' => 'Lead status synchronized successfully.'
            ];
        } catch (BadRequest $e) {
            // Return proper error response
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (\Exception $e) {
            // Log unexpected errors
            $GLOBALS['log']->error('Vanko API Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    private function findLeadByData($data): ?Lead
    {
        $entityManager = $this->getEntityManager();

        // Strategy 1: Find by the internal ID
        if (!empty($data->SFC_CRM_Lead_Identity)) {
            $lead = $entityManager->getRepository('Lead')->where([
                'id' => $data->SFC_CRM_Lead_Identity
            ])->findOne();
            if ($lead) {
                $this->logInfo("Found lead by SFC_CRM_Lead_Identity: " . $lead->getId());
                return $lead;
            }
        }

        // Strategy 2: Find by vanko crm ID
        if (!empty($data->contact_id)) {
            $lead = $entityManager->getRepository('Lead')->where([
                'cVankoCRM' => $data->contact_id
            ])->findOne();
            if ($lead) {
                $this->logInfo("Found lead by contact_id: " . $lead->getId());
                return $lead;
            }
        }
        
        // Strategy 3: Find by name and contact details
        if (!empty($data->first_name) && !empty($data->last_name)) {
            $potentialLead = null;

            // First, try to find a matching ID by email
            if (!empty($data->email)) {
                $potentialLead = $entityManager->getRepository('EmailAddress')->getEntityByAddress($data->email, 'Lead');
            }

            if ($potentialLead) {
                // We found a lead, now let's double-check the name matches to avoid ambiguity.
                if ($potentialLead->get('firstName') == $data->first_name && $potentialLead->get('lastName') == $data->last_name) {
                    $lead = $potentialLead;
                }
            }
            
            if ($lead) {
                $this->logInfo("Found lead by name/contact details: " . $lead->getId());
                return $lead;
            }
        }
        
        $this->logInfo("Could not find a matching lead for the provided data.");
        return null;
    }

    /**
     * Centralized logging helper
     */
    private function logInfo(string $message): void
    {
        $GLOBALS['log']->info("Vanko: {$message}");
    }

}

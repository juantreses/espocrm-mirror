<?php
declare(strict_types=1);

namespace Espo\Custom\Services\LeadLifecycle;

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\LeadLifecycle\State\LeadStateInterface;
use InvalidArgumentException;

// Import all state classes
use Espo\Custom\Services\LeadLifecycle\State\NewLeadState;
use Espo\Custom\Services\LeadLifecycle\State\AssignedLeadState;
use Espo\Custom\Services\LeadLifecycle\State\CallAgainLeadState;
use Espo\Custom\Services\LeadLifecycle\State\MessageToBeSentLeadState;
use Espo\Custom\Services\LeadLifecycle\State\MessageSentLeadState;
use Espo\Custom\Services\LeadLifecycle\State\InvitedLeadState;
use Espo\Custom\Services\LeadLifecycle\State\AppointmentBookedLeadState;
use Espo\Custom\Services\LeadLifecycle\State\AppointmentCancelledLeadState;
use Espo\Custom\Services\LeadLifecycle\State\StillThinkingLeadState;
use Espo\Custom\Services\LeadLifecycle\State\DisqualifiedLeadState;
use Espo\Custom\Services\LeadLifecycle\State\DeadLeadState;
use Espo\Custom\Services\LeadLifecycle\State\BecameClientLeadState;
use Espo\Custom\Services\LeadLifecycle\State\ConvertedLeadState;

class LeadStateFactory
{
    public function __construct(
        private Container $container,
        private InjectableFactory $injectableFactory
    ) {
    }

    public function create(string $status): LeadStateInterface
    {
        $className = match (strtolower($status)) {
            'new' => NewLeadState::class,
            'assigned' => AssignedLeadState::class,
            'call_again' => CallAgainLeadState::class,
            'message_to_be_sent' => MessageToBeSentLeadState::class,
            'message_sent' => MessageSentLeadState::class,
            'invited' => InvitedLeadState::class,
            'appointment_booked' => AppointmentBookedLeadState::class,
            'appointment_cancelled' => AppointmentCancelledLeadState::class,
            'still_thinking' => StillThinkingLeadState::class,
            'disqualified' => DisqualifiedLeadState::class,
            'dead' => DeadLeadState::class,
            'became_client' => BecameClientLeadState::class,
            'converted' => ConvertedLeadState::class,
            default => throw new InvalidArgumentException("No state class found for status: {$status}"),
        };

        // Explicitly get services from container and pass them to InjectableFactory
        // This ensures LeadSideEffectService is resolved correctly since it's registered
        // in services.json with the name "LeadSideEffectService", not "sideEffectService"
        $entityManager = $this->container->get('entityManager');
        $sideEffectService = $this->container->get('LeadSideEffectService');
        
        return $this->injectableFactory->createWith($className, [
            'entityManager' => $entityManager,
            'sideEffectService' => $sideEffectService,
        ]);    
    }
}
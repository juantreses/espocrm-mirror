<?php

namespace Espo\Custom\Logic;

class LeadStateMachine
{
    // Define States based on the diagram
    public const STATE_NEW = 'new';
    public const STATE_ASSIGNED = 'assigned';
    public const STATE_CALL_AGAIN = 'call_again';
    public const STATE_INVITED = 'invited';
    public const STATE_DISQUALIFIED = 'Disqualified';
    public const STATE_DEAD = 'Dead';
    public const STATE_MESSAGE_TO_BE_SENT = 'message_to_be_sent';
    public const STATE_MESSAGE_SENT = 'message_sent';
    public const STATE_APPOINTMENT_BOOKED = 'appointment_booked';
    public const STATE_APPOINTMENT_CANCELLED = 'appointment_cancelled';
    public const STATE_BECAME_CLIENT = 'became_client';
    public const STATE_STILL_THINKING = 'still_thinking';
    public const STATE_CONVERTED = 'Converted';

    /**
     * Map of Allowed Transitions: Current State => [Allowed Next States]
     */
    private const TRANSITION_MAP = [
        self::STATE_NEW => [
            self::STATE_ASSIGNED
        ],
        self::STATE_ASSIGNED => [
            self::STATE_CALL_AGAIN,
            self::STATE_INVITED,
            self::STATE_DISQUALIFIED,
            self::STATE_DEAD
        ],
        self::STATE_CALL_AGAIN => [
            self::STATE_INVITED,
            self::STATE_MESSAGE_TO_BE_SENT,
            self::STATE_DISQUALIFIED
        ],
        self::STATE_MESSAGE_TO_BE_SENT => [
            self::STATE_MESSAGE_SENT
        ],
        self::STATE_MESSAGE_SENT => [
            self::STATE_CALL_AGAIN,
            self::STATE_INVITED,
            self::STATE_DISQUALIFIED
        ],
        self::STATE_INVITED => [
            self::STATE_APPOINTMENT_BOOKED
        ],
        self::STATE_APPOINTMENT_BOOKED => [
            self::STATE_APPOINTMENT_CANCELLED,
            self::STATE_BECAME_CLIENT,
            self::STATE_STILL_THINKING,
            self::STATE_MESSAGE_TO_BE_SENT,
            self::STATE_DISQUALIFIED
        ],
        self::STATE_APPOINTMENT_CANCELLED => [
            self::STATE_CALL_AGAIN,
            self::STATE_DISQUALIFIED,
            self::STATE_APPOINTMENT_BOOKED
        ],
        self::STATE_STILL_THINKING => [
            self::STATE_BECAME_CLIENT,
            self::STATE_DISQUALIFIED
        ],
        self::STATE_BECAME_CLIENT => [
            self::STATE_CONVERTED
        ],
        self::STATE_DISQUALIFIED => [
            self::STATE_BECAME_CLIENT,
            self::STATE_CALL_AGAIN
        ],
        // Final states (Dead, Converted) have no outgoing transitions
        self::STATE_DEAD => [],
        self::STATE_CONVERTED => [],
    ];

    /**
     * Check if a transition is valid.
     */
    public function canTransition(string $fromState, string $toState): bool
    {
        // If states are the same, it's not a transition, usually allowed
        if ($fromState === $toState) {
            return true;
        }

        if (!isset(self::TRANSITION_MAP[$fromState])) {
            // If the state isn't in the map, decide if you want to allow or block.
            // Blocking ensures strict adherence.
            return false;
        }

        return in_array($toState, self::TRANSITION_MAP[$fromState], true);
    }

    /**
     * Get the list of allowed next states.
     */
    public function getAllowedNextStates(string $currentState): array
    {
        return self::TRANSITION_MAP[$currentState] ?? [];
    }
}
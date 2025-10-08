<?php

namespace Espo\Custom\Enums;

enum LeadEventType: string
{
    //Call events
    case CALLED = 'called';
    case INVITED = 'invited';
    case CALL_AGAIN = 'call_again';
    case NO_ANSWER = 'no_answer';
    case WRONG_NUMBER = 'wrong_number';
    case NOT_INTERESTED = 'not_interested';

    //MESSAGE events
    case MESSAGE_TO_BE_SENT = 'message_to_be_sent';
    case MESSAGE_SENT = 'message_sent';

    //Appointment events
    case APPOINTMENT_BOOKED = 'appointment_booked';
    case ATTENDED = 'attended';
    case NO_SHOW = 'no_show';
    case BECAME_CLIENT = 'became_client';
    case NOT_CONVERTED = 'not_converted';
    case STILL_THINKING = 'still_thinking';
    case APPOINTMENT_CANCELLED = 'appointment_cancelled';
    case APPOINTMENT_RESCHEDULED = 'appointment_rescheduled';

    //Coach events
    case BECAME_COACH = 'became_coach';

    public function getLabel(): string
    {
        return match ($this) {
            //Call events
            self::CALLED => 'Called',
            self::INVITED => 'Invited',
            self::CALL_AGAIN => 'Call Again',
            self::NO_ANSWER => 'No Answer',
            self::WRONG_NUMBER => 'Wrong Number',
            self::NOT_INTERESTED => 'Not Interested',
            //Message events
            self::MESSAGE_TO_BE_SENT => 'Message To Be Sent',
            self::MESSAGE_SENT => 'Message Sent',
            //Appointment events
            self::APPOINTMENT_BOOKED => 'Appointment Booked',
            self::ATTENDED => 'Attended',
            self::NO_SHOW => 'No Show',
            self::BECAME_CLIENT => 'Became Client',
            self::NOT_CONVERTED => 'Not Converted',
            self::STILL_THINKING => 'Still thinking',
            self::APPOINTMENT_CANCELLED => 'Appointment Cancelled',
            self::APPOINTMENT_RESCHEDULED => 'Appointment Rescheduled',
            //Coach events
            self::BECAME_COACH => 'Became Coach',
        };
    }

    public static function isValid(string $eventType): bool
    {
        return (bool) self::tryFrom($outcome);
    }
} 
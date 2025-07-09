<?php
namespace datalayer;
use datalayer\interfaces\iWorkflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum RegistrationStatus : string implements iWorkflow {
    case Inactive = "Inactive";
    case Information = "Information Collection";
    case Emergency = "Emergency Contact";
    case AcceptTerms = "Accept Terms";
    case Payment = "Payment Pending";
    case Active = "Active";
    
    /**
     * Returns an array of all possible status values.
     *
     * @return array An array of status values.
     */
    public static function values(): array {
        return array_map(fn ($case) => $case->value, self::cases());
    }
    
    /**
     * Returns the next possible statuses based on the current status.
     *
     * @return array An array of next possible statuses.
     */
    public function nextPossible() : array {
        return match($this) {
            self::Inactive    => [RegistrationStatus::Information],
            self::Information => [RegistrationStatus::Emergency],
            self::Emergency   => [RegistrationStatus::AcceptTerms],
            self::AcceptTerms => [RegistrationStatus::Payment,RegistrationStatus::Information],
            self::Payment     => [RegistrationStatus::Active],
            self::Active      => [RegistrationStatus::Inactive],
            default => [],
        };
    }
   
    /**
     * Checks if the next status is a valid transition from the current status.
     *
     * @param RegistrationStatus $next The next status to check.
     * @return bool True if the transition is valid, false otherwise.
     */
    public function isValidTransition($next) : bool {
        return in_array($next,$this->nextPossible());
    }

    /**
     * Returns the current status.
     *
     * @return RegistrationStatus The current status.
     */
    public function getCurrentStatus() : RegistrationStatus {
        return $this;
    }

    /**
     * Returns the current status name.
     *
     * @return string The name of the current status.
     */
    public function getCurrentStatusName() : string {
        return $this->value;
    }
}

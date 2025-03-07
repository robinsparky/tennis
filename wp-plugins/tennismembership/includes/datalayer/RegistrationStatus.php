<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum RegistrationStatus : string {
    case Inactive = "Inactive";
    case Started = "Registration Started";
    case Information = "Information Collection";
    case Emergency = "Emergency Contact";
    case AcceptTerms = "Accept Terms";
    case Payment = "Payment Pending";
    case Active = "Active";
    

    public static function values(): array {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public function getNextPossible() : array {
        return match($this) {
            self::Inactive    => [RegistrationStatus::Started],
            self::Started     => [RegistrationStatus::Information,RegistrationStatus::Inactive],
            self::Information => [RegistrationStatus::Emergency,RegistrationStatus::Started],
            self::Emergency   => [RegistrationStatus::AcceptTerms,RegistrationStatus::Information],
            self::AcceptTerms => [RegistrationStatus::Payment,RegistrationStatus::Information],
            self::Payment     => [RegistrationStatus::Active],
            self::Active      => [RegistrationStatus::Inactive],
            default => [],
        };
    }

    public function isValidTransition(RegistrationStatus $next) {
        return in_array($next,$this->getNextPossible());
    }
}

<?php
namespace datalayer\handlers\StartRegistrationHandler;
use datalayer\interfaces\iRegistrationHandler;
use TennisClubMembership;
use datalayer\RegistrationStatus;
use datalayer\MemberRegistration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GatherInformation implements iRegistrationHandler {
    private ?iRegistrationHandler $nextHandler = null;

    public function handleRequest(MemberRegistration $request) : void {
        if ($request->getStatus() !== RegistrationStatus::Inactive) {
            throw new \RuntimeException(__('Invalid registration status for start request.', TennisClubMembership::TEXT_DOMAIN));
        }
        
        if( in_array(RegistrationStatus::Inactive,$request->getStatus()->nextPossible())) {
            // Set the status to Started
            $request->setStatus(RegistrationStatus::Information);
            // Launch info form
        }
        elseif($this->nextHandler) {
            $this->nextHandler->handleRequest($request);
        }
        else {
            throw new \RuntimeException(__('No next handler available for registration start.', TennisClubMembership::TEXT_DOMAIN));
        }        
    }

    public function setNextHandler(iRegistrationHandler $nextHandler) : void {
        $this->nextHandler = $nextHandler;
    }
}
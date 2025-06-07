<?php
namespace datalayer\interfaces;
use datalayer\RegistrationStatus;
use datalayer\MemberRegistration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface iRegistrationHandler {
    function handleRequest(MemberRegistration $request) : void;
    function setNextHandler(iRegistrationHandler $nextHandler) : void;
}
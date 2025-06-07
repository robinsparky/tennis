<?php
namespace datalayer\interfaces;
use datalayer\RegistrationStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface iWorkflow {
    public function nextPossible() : array;
    public function isValidTransition($next) : bool;
    public function getCurrentStatus() : RegistrationStatus;
    public function getCurrentStatusName() : string;
}
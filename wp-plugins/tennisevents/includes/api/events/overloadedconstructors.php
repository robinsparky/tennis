<?php
namespace api\events;
use \ReflectionClass;
use \ReflectionMethod;

abstract class OverloadedConstructors {
    public final function __construct() {
       $self = new ReflectionClass($this);
       $constructors = array_filter($self->getMethods(ReflectionMethod::IS_PUBLIC), function(ReflectionMethod $m) {
          return substr($m->name, 0, 11) === '__construct';
       });
       
       if(sizeof($constructors) === 0) {
          trigger_error('The class ' . get_called_class() . ' does not provide a valid constructor.', E_USER_ERROR);
       }
 
       $number = func_num_args();
       $arguments = func_get_args();
       $ref_arguments = array();
       foreach($constructors as $constructor) {
          if(($number >= $constructor->getNumberOfRequiredParameters()) &&
             ($number <= $constructor->getNumberOfParameters())) {
             $parameters = $constructor->getParameters();
             reset($parameters);
             foreach($arguments as $arg) {
                $parameter = current($parameters);
                if($parameter->isArray()) {
                   if(!is_array($arg)) {
                      continue 2;
                   }
                } 
                elseif(($expectedClass = $parameter->getClass()) !== null) {
                   if(!(is_object($arg) && $expectedClass->isInstance($arg))) {
                      continue 2;
                   }
                }
                next($parameters);
             }
             $constructor->invokeArgs($this, $arguments);
             return;
          }
       }
       trigger_error('The required constructor for the class ' . get_called_class() . ' did not exist.', E_USER_ERROR);
    }
 }
 
<?php
namespace api;
// Extending the Exception class
class InvalidTournamentException extends \Exception {}
class BracketHasStartedException extends InvalidTournamentException {}
class ChairUmpireException extends \Exception {}
class TennisConfigurationException extends InvalidTournamentException {}

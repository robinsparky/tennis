<?php
// Extending the Exception class
class InvalidTournamentException extends Exception {}
class ChairUmpireException extends Exception {}
class TennisConfigurationException extends InvalidTournamentException {}
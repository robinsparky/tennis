<?php
namespace api\model;

use TennisClubMembership;
use commonlib\BaseLogger;
use \WP_Error;
use TM_Install;
use \DateTime;
use \InvalidArgumentException;
use \RuntimeException;
use cpt\ClubMembershipCpt;
//use cpt\TennisClubMembershipCpt;
use datalayer\Corporation;
use datalayer\Person;
use datalayer\MemberRegistration;
use datalayer\MembershipType;
use datalayer\appexceptions\InvalidRegistrationException;
/** 
 * Member Registration API
 * @class  RegistrationMgmt
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class RegistrationMgmt {

    /**
     * Create a new registration
     * @param int $seasonId
     * @param Person $person
     * @param MembershipType $memtype
     * @param string $startDate
     * @param string $endDate
     * @param bool $incDir
     * @param bool $receiveEmails
     * @param bool $shareEmail
     * @param string $notes
     * @return MembershipRegistration
     */
    public function createRegistration(int $seasonId, int $userId , MembershipType $memType, string $startDate, string $endDate, bool $incDir = true, bool $receiveEmails=true, bool $shareEmail=true, string $notes='' ) : MemberRegistration {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $result = false;
        try {
            	
            $homeCorpId = esc_attr(get_option(TennisClubMembership::OPTION_HOME_CORPORATION, 0));
            if (0 === $homeCorpId) {
                //$this->log->error_log("$loc - Home corporration id is not set."); 
                throw new InvalidRegistrationException(__("Home corporation id is not set.",TennisClubMembership::TEXT_DOMAIN));
            }
            //$this->log->error_log("$loc: Corporation Id={$homeCorpId}");
            $corp = Corporation::get($homeCorpId);
            if( !isset($corp) ) {				
                throw new InvalidRegistrationException(__( 'Home corporation is not set.', TennisClubMembership::TEXT_DOMAIN ));
            }

            $user = get_user_by('ID',$userId);
            if(false === $user ) {
                throw new InvalidRegistrationException(__( 'No such user id .', TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $reg = MemberRegistration::fromIds($seasonId, $memType->getID(),$userId);
            if(is_null($reg)) throw new InvalidRegistrationException(__( 'Failed to create registration object.', TennisClubMembership::TEXT_DOMAIN ));

             $result = $reg->setReceiveEmails($receiveEmails)
                        && $reg->setIncludeInDir($incDir)
                        && $reg->setShareEmail($shareEmail)
                        && $reg->setStartDate_Str($startDate)
                        && $reg->setEndDate_Str($endDate)
                        && $reg->setNotes($notes);

            if(!$result) throw new InvalidRegistrationException(__( 'Failed to create registration object.', TennisClubMembership::TEXT_DOMAIN ));
        }
        catch (RuntimeException | InvalidRegistrationException | InvalidArgumentException $ex ) {
           throw $ex;
        }
        return $reg;
    }
}
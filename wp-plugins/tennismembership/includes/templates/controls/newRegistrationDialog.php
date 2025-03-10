<?php
//use TennisClubMembership;
use datalayer\MembershipType;

$memshpTypeDropDown = "<select class='tennis-add-registration membership-type' name='registrationtype'>";
$ctr = 0;
foreach(Membershiptype::allTypes() as $tp) {
    $selected = ($ctr++ === 0) ? "selected='selected'" : "";
    $memshpTypeDropDown .= "<option value='{$tp->getID()}' {$selected}>{$tp->getName()}</option>";
}
$memshpTypeDropDown .= "</select>";

$today = (new DateTime('now'))->format('Y-m-d');
?>
<dialog class="tennis-add-registration-dialog">
<form method="dialog" class="tennis-add-registration-form">
    <label for="title"><b>Title</b>
    <input type="text" class="tennis-add-registration" name="title" required/>
    </label>
    <fieldset>
        <label><b>Membership Type:</b><?php echo $memshpTypeDropDown;?>
        </label>        
        <label for="startdate"><b>Starts:</b></label>
        <input type="date" class="tennis-add-registration" value="<?php echo $today?>" name="startdate" required/>
        <label for="enddate"><b>Expires:</b></label>
        <input type="date" class="tennis-add-registration" value="<?php echo $today?>" name="enddate" required/>
    </fieldset>
<hr/>
<div>
    <button class="tennis-add-registration-close" formmethod="dialog" value="submitted">Save</button> 
    <button class="tennis-add-registration-close" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
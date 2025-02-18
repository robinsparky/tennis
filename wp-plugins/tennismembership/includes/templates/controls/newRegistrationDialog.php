<?php
//use TennisClubMembership;
use datalayer\MembershipType;

$memshpTypeDropDown = "<select class='tennis-add-event event-type' name='eventtype'>";
$ctr = 0;
foreach(Membershiptype::allTypes() as $tp) {
    $selected = ($ctr++ === 0) ? "selected='true'" : "";
    $memshpTypeDropDown .= "<option value='{$tp->getID()}' {$selected}>{$tp->getName()}</option>";
}
$memshpTypeDropDown .= "</select>";

$today = (new \DateTime('now'))->format('Y-m-d');
?>
<dialog class="tennis-add-event-dialog root">
<form method="dialog" class="tennis-add-event-form root">
    <label for="title"><b>Title</b>
    <input type="text" class="tennis-add-event" name="title" required/>
    </label>
    <fieldset>
        <label><b>Membership Type:</b><?php echo $memshpTypeDropDown;?>
        </label>        
        <label for="startdate"><b>Start:</b></label>
        <input type="date" class="tennis-add-event" value="<?php echo $today?>" name="startdate" required/>
        <label for="enddate"><b>End:</b></label>
        <input type="date" class="tennis-add-event" value="<?php echo $today?>" name="enddate" required/>
    </fieldset>
<hr/>
<div>
    <button class="tennis-add-event-close root" formmethod="dialog" value="submitted">Save</button> 
    <button class="tennis-add-event-close root" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
<?php
use datalayer\MembershipSuperType;
use TennisClubMembership;

$memberSuperTypeDropDown = "<select name='membersupertype'>";
foreach(MembershipSuperType::find() as $super) {
    $selected = $super->getName() == TennisClubMembership::PIVOT ? "selected='true'" : "";
    $memberSuperTypeDropDown .= "<option value='{$super->getID()}' {$selected}>{$super->getName()}</option>";
}
$memberSuperTypeDropDown .= "</select>";

$today = (new \DateTime('now'))->format('Y-m-d');
?>
<dialog class="tennis-add-membership-dialog root">
<form method="dialog" class="">
    <fieldset>
        <label><b>Event Super Type:</b><?php echo $memberSuperTypeDropDown;?>
        </label> 
        <label for="name"><b>Name</b>
        <input type="text" class="" name="name" required/>
        </label>
    </fieldset>
<hr/>
<div>
    <button class="tennis-add-event-close root" formmethod="dialog" value="submitted">Save</button> 
    <button class="tennis-add-event-close root" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
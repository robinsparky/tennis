<?php
use datalayer\MembershipCategory;
use TennisClubMembership;

$memberCategoryDropDown = "<select name='membersupertype'>";
foreach(MembershipCategory::find() as $cat) {
    $selected = $cat->getName() == TennisClubMembership::PIVOT ? "selected='true'" : "";
    $memberSuperTypeDropDown .= "<option value='{$cat->getID()}' {$selected}>{$cat->getName()}</option>";
}
$memberCategoryDropDown .= "</select>";

$today = (new \DateTime('now'))->format('Y-m-d');
?>
<dialog class="tennis-add-membership-dialog root">
<form method="dialog" class="">
    <fieldset>
        <label><b>Membership Type Category:</b><?php echo $memberCategoryDropDown;?>
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
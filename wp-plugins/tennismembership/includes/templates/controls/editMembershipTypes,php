<?php
use datalayer\MembershipSuperType;
use datalayer\MembershipType;

$superTypes = MembershipSuperType::find();
?>

<dialog class="tennis-list-membership-dialog root">
<form method="dialog" class="tennis-list-membership-form">

<?php
foreach(MembershipType::find() as $memType) {
$memberSuperTypeDropDown = "<select name='membersupertype'>";
foreach($superTypes as $super) {
    $selected = $super->getName() == $memType->getSuperTypeId() == $super->getID() ? "selected='true'" : "";
    $memberSuperTypeDropDown .= "<option value='{$super->getID()}' {$selected}>{$super->getName()}</option>";
}
$memberSuperTypeDropDown .= "</select>";
?>

<fieldset>
    <label><b>Event Super Type:</b><?php echo $memberSuperTypeDropDown;?></label> 
    <label for="name"><b>Name</b>
    <input type="hidden" name="supertypeID" value="<?php $memType->getSuperTypeId();?>"/>
    <input id="<?php $memType->getID();?>" type="text" class="" name="name" required value="<?php $memType->getName();?>"/>
    </label>
</fieldset>
<?php } ?>
<hr/>
<div>
    <button class="tennis-list-membershiptype-close root" formmethod="dialog" value="submitted">Save</button> 
    <button class="tennis-list-membershiptype-close root" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>

<button class="tennis-add-membershipype root" formmethod="dialog" value="submitted">Add Membership Type</button> 
<?php
   $path = wp_normalize_path(TM()->getPluginPath() . "includes/templates/controls/addMembershipType.php");
   include($path);
?>
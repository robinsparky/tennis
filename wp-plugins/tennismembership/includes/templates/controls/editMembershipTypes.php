<?php
use datalayer\MembershipCategory;
use datalayer\MembershipType;

$cats = MembershipCategory::find();
?>

<dialog class="tennis-list-membership-dialog root">
<form method="dialog" class="tennis-list-membership-form">

<?php
foreach(MembershipType::find() as $memType) {
$categoryDropDown = "<select name='membertypecategory'>";
foreach($cats as $cat) {
    $selected = $cat->getName() == $memType->getCategory() == $cat->getID() ? "selected='true'" : "";
    $categoryDropDown .= "<option value='{$cat->getID()}' {$selected}>{$cat->getName()}</option>";
}
$categoryDropDown .= "</select>";
?>
<fieldset>
    <label><b>Membership Type Category:</b><?php echo $categoryDropDown;?></label> 
    <label for="name"><b>Name</b>
    <input type="hidden" name="category" value="<?php $memType->getCategory();?>"/>
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

<button class="tennis-edit-membershiptype root" formmethod="dialog" value="submitted">Edit Membership Type</button> 
<?php
   $path = wp_normalize_path(TM()->getPluginPath() . "includes/templates/controls/editMembershipType.php");
   include($path);
?>
<button class="tennis-add-membershiptype root" formmethod="dialog" value="submitted">Add Membership Type</button> 
<?php
   $path = wp_normalize_path(TM()->getPluginPath() . "includes/templates/controls/addMembershipType.php");
   include($path);
?>
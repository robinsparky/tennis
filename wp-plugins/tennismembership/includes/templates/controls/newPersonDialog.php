<?php
//use TennisClubMembership;
use datalayer\Genders;
$today = (new DateTime('now'))->format('Y-m-d');
?>
<dialog class="tennis-add-person-dialog">
<form method="dialog" class="tennis-add-person-form">
    <label for="title"><b>Title</b>
    <input type="text" class="tennis-add-person" name="title" required/>
    </label>
    <fieldset>       
        <label for="firstname"><b>First Name:</b></label>
        <input type="text" class="tennis-add-person" value="" name="firstname" required/>
        <label for="lastname"><b>Last Name:</b></label>
        <input type="text" class="tennis-add-person" value="" name="lastname" required/>
        <label for="email"><b>Email:</b></label>
        <input type="text" class="tennis-add-person" value="" name="email" required/>
        <label><b>Gender:</b><?php Genders::getGendersDropDown();?></label> 
    </fieldset>
<hr/>
<div>
    <button class="tennis-add-person-close" formmethod="dialog" value="submitted">Save</button> 
    <button class="tennis-add-person-close" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
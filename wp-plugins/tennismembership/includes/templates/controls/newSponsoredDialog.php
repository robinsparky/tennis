<?php
//use TennisClubMembership;
use datalayer\Genders;
$today = (new DateTime('now'))->format('Y-m-d');
$genderSelect = Genders::getGendersDropDown();
?>
<dialog class="membership add-sponsored" data-sponsorid="<?php echo $sponsorId;?>" data-sponsorname="<?php echo $sponsorName;?>">
    <h3>Sponsored by <?php echo $sponsorName;?></h3>
<form method="dialog" class="membership add-sponsored" data-sponsorid="<?php echo $sponsorId; ?>" data-sponsorname="<?php echo $sponsorName; ?>">
    <fieldset>       
        <label for="firstname"><b>First Name:</b></label>
        <input type="text" class="membership add-sponsored first-name" value="" name="firstname" />
        <br/>
        <label for="lastname"><b>Last Name:</b></label>
        <input type="text" class="membership add-sponsored last-name" value="" name="lastname" />
        <br/>
        <label for="email"><b>Email:</b></label>
        <input type="email" class="membership add-sponsored home-email" value="" name="email"/>
        <br/>
        <label><b>Gender:</b><?php echo $genderSelect;?></label> 
        <br/>
        <label for="birthdate"><b>Birthdate:</b></label>
        <input type="date" class="membership add-sponsored birth-date" value="" name="birthdate" />
        <br/>
        <label for="homephone"><b>Phone:</b></label>
        <input type="tel" class="membership add-sponsored home-phone" value="" name="homephone"/>
    </fieldset>
<hr/>
<div>
    <button class="membership add-sponsored-close" formmethod="dialog" value="submitted">Save</button> 
    <button class="membership add-sponsored-close" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
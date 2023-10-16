<?php
use datalayer\Event;
use datalayer\EventType;
use datalayer\Format;
use datalayer\MatchType;
use datalayer\ScoreType;
use datalayer\GenderType;
?>
<dialog class="tennis-edit-event-dialog root" data-eventid="<?php echo $eventId;?>">
<form method="dialog" class="tennis-edit-event-form root">
    <input type="hidden"  name="eventId" value="<?php echo $eventId;?>"/>
    <input type="hidden"  name="postId" value="<?php echo $postId;?>"/>
    <label for="title"><b>Title</b>
    <input type="text" class="tennis-edit-event" name="title" value="<?php echo $eventTitle; ?>" required/>
    </label>
    <fieldset>
        <label for="startdate"><b>Start:</b></label>
        <input type="date" class="tennis-edit-event" name="startdate" value="<?php echo $startDate;?>" required/>
        <label for="enddate"><b>End:</b></label>
        <input type="date" class="tennis-edit-event" name="enddate" value="<?php echo $endDate;?>" required/>
    </fieldset>
<hr/>
<div>
    <button class="tennis-edit-event-close root" formmethod="dialog" value="submitted">Submit</button> 
    <button class="tennis-edit-event-close root" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
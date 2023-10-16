<?php
use datalayer\Event;
use datalayer\EventType;
use datalayer\Format;
use datalayer\MatchType;
use datalayer\ScoreType;
use datalayer\GenderType;
?>
<dialog class="tennis-add-event-dialog root">
<form method="dialog" class="tennis-add-event-form root">
    <label for="title"><b>Title</b>
    <input type="text" class="tennis-add-event" name="title" required/>
    </label>
    <fieldset>
        <label for="startdate"><b>Start:</b></label>
        <input type="date" class="tennis-add-event" name="startdate" required/>
        <label for="enddate"><b>End:</b></label>
        <input type="date" class="tennis-add-event" name="enddate" required/>
    </fieldset>
<hr/>
<div>
    <button class="tennis-add-event-close root" formmethod="dialog" value="submitted">Submit</button> 
    <button class="tennis-add-event-close root" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
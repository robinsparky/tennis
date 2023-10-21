<?php
use datalayer\EventType;

$eventTypeDropDown = "<select class='tennis-add-event event-type' name='eventtype'>";
foreach(EventType::AllTypes() as $key=>$value) {
    $selected = ($key === EventType::TOURNAMENT) ? "selected='true'" : "";
    $eventTypeDropDown .= "<option value='{$key}' {$selected}>{$value}</option>";
}
$eventTypeDropDown .= "</select>";

$today = (new \DateTime('now'))->format('Y-m-d');
?>
<dialog class="tennis-add-event-dialog root">
<form method="dialog" class="tennis-add-event-form root">
    <label for="title"><b>Title</b>
    <input type="text" class="tennis-add-event" name="title" required/>
    </label>
    <fieldset>
        <label><b>Event Type:</b><?php echo $eventTypeDropDown;?>
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
<?php
use datalayer\Event;
use datalayer\EventType;
use datalayer\Format;
use datalayer\MatchType;
use datalayer\ScoreType;
use datalayer\GenderType;

//Gender Type Drop Down
$genderTypeDropDownNew = "<select name='GenderTypesNew' class='tennis-add-event gender_selector_new'>";
foreach( GenderType::AllTypes() as $key=>$value ) {
    $genderTypeDropDownNew .= "<option value='{$key}' value='default'>{$value}</option>";
}
$genderTypeDropDownNew .= "</select>";

//Match type drop down
$matchTypeDropDownNew = "<select name='MatchTypesNew' class='tennis-add-event match_type_selector_new'>";
foreach( MatchType::AllTypes() as $key=>$value ) {
    $matchTypeDropDownNew .= "<option value='{$key}'>{$value}</option>";
}
$matchTypeDropDownNew .= "</select>";

//Format Drop Down
$formatDropDownNew = "<select name='AllFormatsNew' class='tennis-add-event format_selector_new'>";
foreach( Format::AllFormats() as $key=>$value ) {
    $formatDropDownNew .= "<option value='{$key}'>{$value}</option>";
}
$formatDropDownNew .= "</select>";

//Score Rules Drop Down
$scoreRulesDropDownNew = "<select name='ScoreRulesNew' class='tennis-add-event score_rules_selector_new'>";
foreach(ScoreType::get_instance()->getRuleDescriptions() as $key=>$value ) {
    $scoreRulesDropDownNew .= "<option value='{$key}'>{$value}</option>";
}
$scoreRulesDropDownNew .= "</select>";
?>
<dialog class="tennis-add-event-dialog leaf" data-parentid="<?php echo $eventId;?>">
<form method="dialog" class="tennis-add-event-form leaf">
    <input type="hidden"  name="parentEventId" value="<?php echo $event->getID();?>"/>
    <label for="title">Title
    <input type="text" class="tennis-add-event" name="title"/>
    </label>
    <fieldset>
        <legend><b>Important Dates</b></legend>
        <label for="signupby">Signup Deadline:</label>
        <input type="date" class="tennis-add-event" name="signupby" required/>
        <label for="startdate">Start:</label>
        <input type="date" class="tennis-add-event" name="startdate" required/>
        <label for="enddate">End:</label>
        <input type="date" class="tennis-add-event" name="enddate" required/>
    </fieldset>
<div>
    <legend><b>Match Specifications</b></legend>
    <fieldset>
        <div>
        <label>Gender:
        <?php echo $genderTypeDropDownNew;?>
        </label>
        <label>Match Type:
        <?php echo $matchTypeDropDownNew;?>
        </label>
        <label>Format:
        <?php echo $formatDropDownNew;?>
        </label>
        </div>
    </fieldset>
</div>
<div>
    <fieldset>
        <legend><b>Scoring</b></legend>
        <?php echo $scoreRulesDropDownNew;?>
        <label>
    </fieldset>
</div>
<hr/>
<div>
    <button class="tennis-add-event-close leaf" formmethod="dialog" value="submitted">Submit</button> 
    <button class="tennis-add-event-close leaf" formmethod="dialog" value="cancelled">Cancel</button>
</div>
</form>
</dialog>
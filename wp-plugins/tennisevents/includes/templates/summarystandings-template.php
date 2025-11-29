<table id="tennis-score-summary-id" class="tennis-score-summary">
<caption>Detail Results by Team/Squad for <?php echo $tournamentName;?>&#58;&nbsp;<?php echo $bracketName; ?></caption>
<thead><tr><th>&#x23;</th><th>Entrant</th>
<?php 
for( $r = 1; $r <= $numRounds; $r++) :
?>
<th><?php echo $titlePrefix;?>&nbsp;<?php echo $r;?></th>
<?php
endfor //rounds in table head
?>
<th>Points</th><th>Games</th></tr></thead>
    <?php
    //$ctr = 0;
    foreach($summaryTable as $entrantSummary ) :
    ?>
    <!-- teamSquad -->
    <tr class="entrant-match-summary" data-entrant="<?php echo $entrantSummary["position"];?>">
        <td class="entrant-position"><?php echo $entrantSummary["position"]; ?></td><td class="entrant-name"><?php echo $entrantSummary["name"]; ?></td>
        <?php
            for( $r = 1; $r <= $numRounds; $r++) :
        ?>
            <td class="matcheswon" data-roundnum="<?php echo $r;?>"><?php echo $entrantSummary[$r];?></td>
        <?php
            endfor //round
        ?>
        <td class="points"><?php echo $entrantSummary["totalPoints"];?></td>
        <td class="games"><?php echo  $entrantSummary["totalGames"];?></td>
    </tr>
    <!-- /teamSquad -->
    <?php
    endforeach //teamSquad
    ?>
<tfoot id="tennis-summary-foot">
<td colspan="2" id="bracket-summary"><span><?php echo $bracketSummary["completedMatches"] . " of " . $bracketSummary["totalMatches"]; echo ' Matches Completed';?></span></td>
<?php
for($r = 1; $r<=$numRounds; $r++) : ?>
<td id="summary-by-round-<?php echo $r; ?>" data-bracketsummary="<?php echo $bracketSummary["byRound"][$r];?>"><?php echo $bracketSummary["byRound"][$r]; ?></td>
<?php endfor; ?>
<td colspan="2"><span class="tennis-champion"><?php if( !empty($bracketSummary["champion"]) ) echo $bracketSummary["champion"];?></span></td>
</tfoot></table>
<table class="team-standings-summary-table">
    <caption>Team Standings for <?php echo $tournamentName;?>&#58;&nbsp;<?php echo $bracketName; ?></caption>
    <thead><tr><th>Team</th><th>Total Points</th><th>Total Games</th></tr></thead>
    <tbody>
<?php 
$ctr = 0;
foreach($teamStandings as $teamNum => $teamStats ) : ++$ctr?>
    <tr id='<?php echo "{$ctr}_place";?>'>
        <td class="team-name"><?php echo $teamStats['teamName'];?></td>
        <td class="points"><?php echo $teamStats['points'];?></td>
        <td class="games"><?php echo $teamStats['games'];?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
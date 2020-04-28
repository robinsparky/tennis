<table class="tennis-point-summary">
<caption>Summary of <?php echo $tournamentName;?>&#58;&nbsp;<?php echo $bracketName; ?> Bracket</caption>
<thead><tr><th>&#x23;</th><th>Entrant</th>
<?php 
for( $r = 1; $r <= $numRounds; $r++) :
?>
<th>Rnd&nbsp;<?php echo $r;?></th>
<?php
endfor //rounds in table head
?>
<th>Points</th><th>Games</th></tr></thead>
<tbody>
    <?php
    $ctr = 0;
    foreach($summaryTable as $entrantSummary ) :
    ?>
    <!-- entrant -->
    <tr class="entrant-match-summary" data-entrant="<?php echo $entrantSummary["position"];?>">
    <td class="entrant-position"><?php echo $entrantSummary["position"]; ?></td><td class="entrant-name"><?php echo $entrantSummary["name"]; ?></td>
    <?php
        for( $r = 1; $r <= $numRounds; $r++) :
    ?>
        <td class="matcheswon" data-roundnum="<?php echo $r;?>"><?php echo $entrantSummary[$r];?></td>
    <?php
        endfor //round
    ?>
    <td class="points"><?php echo $entrantSummary["totalPoints"]; ?></td>
    <td class="games"><?php echo  $entrantSummary["totalGames"];?></td></tr>
    <!-- /entrant -->
    <?php
        endforeach //entrant
    ?>
</tbody>
<tfooter>
<td colspan="2"><span><?php echo $bracketSummary["completedMatches"] . " of " . $bracketSummary["totalMatches"]; echo ' Matches Completed';?></span></td>
<?php
for($r = 1; $r<=$numRounds; $r++) : ?>
<td><?php echo $bracketSummary["byRound"][$r]; ?></td>
<?php endfor; ?>
<td colspan="2"><span><?php if( !empty($bracketSummary["champion"]) ) echo "Champion: " . $bracketSummary["champion"];?></span></td>
</tfooter></table>
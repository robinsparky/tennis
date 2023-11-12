<?php 
use api\ELO;
use datalayer\Match;
?>
<table id='<?php echo $eventId; ?>' class='tennis-ladder summary'>
<tr>
    <th><b>Name</b></th>
    <?php $numCells = count(array_keys($entrantMatches));
        foreach(array_keys($entrantMatches) as $nameCol) { ?>
        <th><?php echo $nameCol; ?></th>
    <?php } ?>
    <th><b>Points</b></th>
    <th><b>Games</b></th>
</tr>
<?php 
    $entrantSummary = $umpire->getEntrantSummary($bracket);
    $row = 0;
    $pos = 0;
    foreach($entrantMatches as $name=>$matches) { 
        foreach($entrantSummary as $summary) {
            if($summary["name"] === $name) {
                $pos=$summary["position"];
                break;
            }
        }
?>
        <tr id='row<?php $col=0; echo ++$row;?>' class='entrant-match-summary' data-entrant='<?php echo $pos;?>'>
        <td class='entrant-name'><?php echo $name;?></td>
        <?php 
            $numMatches = count($matches);
            while( $col <= $numMatches ) {
                ++$col;
                if($row === $col) { ?>
            <td id='<?php  echo "({$row},{$col})";?>' class='matcheswon'>&mdash;</td>
            <?php continue;
            } else { 
                $match = array_shift($matches);
                if(!isset($match)) {
                    $this->log->error_log("Ran out of matches!! at {$row},{$col} for name={$name}");
                    throw new RuntimeException("Ran out of matches!! at {$row},{$col} for name={$name}");
                    continue;
                }
                if(!($match instanceof Match)) {
                    $this->log->error_log($match,"SUMMARY LADDER Encountered non match at {$row},{$col} for name={$name}");
                    throw new RuntimeException("Encountered non match at {$row},{$col} for name={$name}");
                }
                extract($umpire->getMatchSummary($match));
                $winner  = $umpire->matchWinner( $match );
                $winnerName  = is_null( $winner ) ? 'tba': $winner->getName();
                $points = ($winnerName === $name) ? 1: 0;
                $mess = "{$points}";
                // if( $winnerName === $name && $winnerName !== 'tba') {
                //     $points = 1;
                //     $rating = ELO::get_instance()->calcRating(0,0,true);
                //     $mess = "{$rating[0]} {$points} {$rating[1]}";
                // }
                // elseif($winnerName !== 'tba') {
                //     $points=0;
                //     $rating = ELO::get_instance()->calcRating(0,0,false);
                //     $mess = "{$rating[0]} {$points} {$rating[1]}";
                // }
            ?>
            <td id='<?php  echo "({$row},{$col})" ?>' class='matcheswon' <?php echo " data-bracketnum='{$bracketnum}'"; echo " data-roundnum='{$match->getRoundNumber()}'"; echo " data-matchnum='{$match->getMatchNumber()}'"; ?>><?php echo $mess; ?></td>
            <?php } ?> 
        <?php } ?>
        <?php 
            foreach($entrantSummary as $summary) {
                if($summary["name"] === $name) {
                    $totalPoints = $summary["totalPoints"];
                    $totalGames = $summary["totalGames"];
                    $totalSets = $summary["totalSets"];
                    echo "<td class='points'>{$totalPoints}</td><td class='games'>{$totalGames}</td>";
                    break;
                }
            }
        ?>
        </tr>
<?php } ?>
</table>
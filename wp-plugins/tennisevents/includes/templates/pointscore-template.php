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
    foreach( $matchesByEntrant as $player => $matchInfo) :
        ++$ctr;
        $totalGames = 0;
        $totalPoints = 0;
        $entrant = $matchInfo[0];
        $matches = $matchInfo[1];
    ?>
    <!-- entrant -->
    <tr class="entrant-match-summary" data-entrant="<?php echo $entrant->getPosition();?>">
    <td class="entrant-position"><?php echo $entrant->getPosition(); ?></td><td class="entrant-name"><?php echo $player; ?></td>
    <?php
        $totalSetsWon = 0;
        for( $r = 1; $r <= $numRounds; $r++) :
            $totalMatchesWon = 0;
            foreach( $matches as $match ) {
                if( $match->getRoundNumber() != $r ) continue;
                extract( $umpire->getMatchSummary( $match ) );
                if( $player === $umpire->getHomePlayer( $match ) ) {
                    $totalGames += $homeGamesWon;
                    $totalSetsWon += $homeSetsWon;
                    if( $andTheWinnerIs === 'home') {
                        ++$totalMatchesWon;
                        $totalPoints += $totalMatchesWon * 2;
                    }
                }
                elseif( $player === $umpire->getVisitorPlayer( $match ) ) {
                    $totalGames += $visitorGamesWon;
                    $totalSetsWon += $visitorSetsWon;
                    if( $andTheWinnerIs === 'visitor') {
                        ++$totalMatchesWon;
                        $totalPoints += $totalMatchesWon * 2;
                    }
                }
            }
    ?>
        <td class="matcheswon" data-roundnum="<?php echo $r;?>"><?php echo $totalMatchesWon;?></td>
    <?php
        endfor //round
    ?>
    <td class="points"><?php echo $totalPoints; ?></td>
    <td class="games"><?php echo  $totalGames;?></td></tr>
    <!-- /entrant -->
    <?php
        endforeach //entrant
    ?>
</tbody>
</table>
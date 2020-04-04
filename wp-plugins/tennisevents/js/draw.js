(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Draw");
        console.log(tennis_draw_obj);

        var longtimeout = 60000;
        var shorttimeout = 5000;

        let ajaxFun = function( drawData ) {
            console.log('Draw Management: ajaxFun');
            let reqData =  { 'action': tennis_draw_obj.action      
                           , 'security': tennis_draw_obj.security 
                           , 'data': drawData };    
            console.log("Parameters:");
            console.log( reqData );

            // Send Ajax request with data 
            let jqxhr = $.ajax( { url: tennis_draw_obj.ajaxurl    
                                , method: "POST"
                                , async: true
                                , data: reqData
                                , dataType: 'json'
                        ,beforeSend: function( jqxhr, settings ) {
                            $(sig).show();
                            $(sig).html('Working...');
                        }})
                    .done( function( res, jqxhr ) {
                        console.log("done.res:");
                        console.log(res);
                        if( res.success ) {
                            console.log('Success (res.data):');
                            console.log(res.data);
                            //Do stuff with data...
                            applyResults(res.data.returnData);
                            $(sig).html( res.data.message );
                        }
                        else {
                            console.log('Done but failed (res.data):');
                            console.log(res.data);
                            var entiremess = res.data.message + " ...<br/>";
                            for(var i=0; i < res.data.exception.errors.length; i++) {
                                entiremess += res.data.exception.errors[i][0] + '<br/>';
                            }
                            $(sig).addClass('tennis-error');
                            $(sig).html(entiremess);
                        }
                    })
                    .fail( function( jqXHR, textStatus, errorThrown ) {
                        console.log("Fail: %s -->%s", textStatus, errorThrown );
                        var errmess = "Fail: status='" + textStatus + "--->" + errorThrown;
                        errmess += jqXHR.responseText;
                        console.log('jqXHR:');
                        console.log(jqXHR);
                        $(sig).addClass('tennis-error');
                        $(sig).html(errmess);
                    })
                    .always( function() {
                        console.log( "always" );
                        setTimeout(function(){
                                    $(sig).html('');
                                    $(sig).removeClass('tennis-error');
                                    $(sig).hide();
                                }, shorttimeout);
                    });
            
            return false;
        }
        
        function formatDate( date ) {
            var d = new Date(date),
                month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();
        
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
        
            return [year, month, day].join('-');
        }

        function applyResults( data ) {
            console.log( "applyResults" );
            data = data || [];
            console.log(data);
            let numRounds = $('.bracketdraw').attr('data-numrounds');
            for( let rn = 1; rn <= numRounds + 1; rn++) {
                matches = data.filter( match => {
                                        return rn == match.roundNumber;
                                    });
                if( matches.length < 1 ) break; //quit if no more data

                matches = matches.sort( function(m1,m2) {
                    //descending sort because 'places' seems to be in reverse order???
                    if( m1.matchNumber < m2.matchNumber) {return 1}
                    if( m1.matchNumber > m2.matchNumber) {return -1}
                    return 0;
                });

                //Get all td's for this round number
                places = $('.bracketdraw td[data-round= '+ rn + ']');

                match = null;
                places.each( function(index) {
                    let pos = index + 1;
                    if ( pos % 2 == 0) {
                        //Even Number
                        //$(this).text('');
                        $contents = formatContent( match.visitorEntrant, match );
                        $(this).append($contents);
                    } else {    
                        //Odd Number s/b home entrant
                        match = matches.pop();
                        //$(this).text('');
                        $contents = formatContent( match.homeEntrant, match );
                        $score = formatScore( match );
                        $(this).append($contents);
                        $(this).append($score);
                    } 
                    if( match.matchNumber % 2 == 0 ) {
                        $(this).addClass('match-color-even');
                        //$(this).css("border-bottom","1px solid red");
                    }
                    else {
                        $(this).addClass('match-color-odd');
                    }
                });
            }
        }

        function formatContent( name, match ) {
            $ret = $('<div>');
            if( name === match.winner && !match.isBye ) {
                $name = $('<span>',{class: 'matchwinner'}).text(name);
            }
            else {
                $name = $('<span>').text(name);
            }
            // console.log($name);
            $ret.append($name);
            return $ret;
        }

        function formatScore( match ) {
            console.log( "formatScore.................");
            console.log( match.sets );
            $container = $('<div>', {class: 'tennis-score-container'});

            if( match.scores === '' ) {
                $container.append("<!-- NO SCORES YET -->");
                return $container;
            } 
            $container.css("width","50%").css("margin","0 auto");
            $container.css("border","1px solid blue");
            //Home scores first
            $homeWinsContainer = $('<div>',{class: 'home-score-container'});
            for( set of match.sets ) {
                console.log("Home Set: %d", set.setNumber);
                $homeWins = $('<span>',{ id: set.setNumber
                                    , class: "home-wins"
                                    , text: set.homeWins
                                    });
                if(set.homeTieBreakPoints > 0 ) {
                    $homeWins.append("<sup>" + set.homeTieBreakPoints + "</sup>");
                }

                // console.log("homeWins: %d", set.homeWins);
                // console.log($homeWins);
                $homeWinsContainer.append($homeWins);
            };
    
            //Visitor scores
            $visitorWinsContainer = $('<div>',{class: 'visitor-score-container'});
            for( set of match.sets ) {
                $visitorWins = $('<span>',{ id: set.setNumber
                                        , class: "visitor-wins"
                                        , text: set.visitorWins
                                });
                if(set.visitorTieBreakPoints > 0 ) {
                    $visitorWins.append( "<sup>" + set.visitorTieBreakPoints + "</sup>");
                } 
                $visitorWinsContainer.append($visitorWins);
            };

            $container.append( $homeWinsContainer );
            $container.append( $visitorWinsContainer );
            console.log( $container );
            return $container;
        }

        //Load up the entrants into the draw
        let eventId = $('.bracketdraw').attr('data-eventid');
        let bracketName = $('.bracketdraw').attr('data-bracketname');
        if( tennis_draw_obj.matches ) {
            applyResults( tennis_draw_obj.matches);
        }
        else {
            ajaxFun( {"task": "getdata", "eventId": eventId, "bracketName": bracketName } );
        }
    });
})(jQuery);
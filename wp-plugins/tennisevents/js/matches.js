(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Matches");
        console.log(tennis_draw_obj);

        var longtimeout = 60000;
        var shorttimeout = 5000;
        var winnerclass = 'matchwinner';

        let ajaxFun = function( matchData ) {
            console.log('Draw Management: ajaxFun');
            let reqData =  { 'action': tennis_draw_obj.action      
                           , 'security': tennis_draw_obj.security 
                           , 'data': matchData };    
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
            data = data || [];
            console.log("Apply results:");
            console.log( data );
            if( $.isArray(data) ) {
                console.log("Data is an array");
                let task = data['task'];
                switch(task) {
                    case 'changehome':
                        updateHome( data );
                        break;
                    case 'changevisitor':
                        updateVisitor( data );
                        break;
                    case 'recordscore':
                        updateScore( data );
                        break;
                    case 'setcomments':
                        updateComments( data );
                        break;
                    default:
                        console.log("Unknown task from server: '%s'", task);
                        break;
                }
            }
            else if( typeof data == "string") {
                console.log("Data is a string");
                switch(data) {
                    case 'createPrelim':
                        $('#approveDraw').prop('diabled', false );
                        $('#createPrelim').prop('disabled', true );
                        $('#removePrelim').prop('disabled', false );
                        window.location.reload(); 
                        break;
                    case 'reset':
                        $('#approveDraw').prop('diabled', true );
                        $('#createPrelim').prop('disabled', false );
                        $('#removePrelim').prop('disabled', true );
                        window.location.reload(); 
                        break;
                    case 'approve': 
                        $('#approveDraw').prop('diabled', true );
                        $('#createPrelim').prop('disabled', true );
                        $('#removePrelim').prop('disabled', true );
                        window.location.reload();
                        break;
                }
            }
            else {
                console.log("Data is an object");
                let task = data.task;
                switch(task) {
                    case 'changehome':
                        updateHome( data );
                        break;
                    case 'changevisitor':
                        updateVisitor( data );
                        break;
                    case 'savescore':
                        updateScore( data );
                        break;
                    case 'setcomments':
                        updateComments( data );
                        break;
                    case 'defaultentrant':
                        updateStatus( data );
                        break;
                    case 'setmatchstart':
                        updateMatchStart( data );
                        break;
                    default:
                        console.log("Unknown task from server: '%s'", task);
                        break;
                }
            }
        }

        function updateMatchStart( data ) {
            console.log('updateMatchStart');
            
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.matchstart').text(data.matchstartdate + " " + data.matchstarttime);
            $matchEl.children('.matchstart').fadeIn( 500 );
            $matchEl.children('.changematchstart').hide( 500 );
        }

        function updateHome( data ) {
            console.log('updateHome');
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.homeentrant').text(data.player);
        }

        function updateVisitor( data ) {
            console.log('updateVisitor');
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.visitorentrant').text(data.player);
        }

        function updateScore( data ) {
            console.log('updateScore');
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            console.log( "Score table: %s; status=%s", data.score, data.status );
            $matchEl.children('.matchscore').empty();
            $matchEl.children('.matchscore').append( data.score );
            $matchEl.children('.showmatchscores').fadeIn( 500 );
            $matchEl.children('.changematchscores').hide( 500 );
            $matchEl.children('.matchstatus').text(data.status);
            switch( data.winner ) {
                case 'home':
                    $matchEl.children('.homeentrant').addClass(winnerclass)
                    break;
                case 'visitor':
                    $matchEl.children('.visitorentrant').addClass(winnerclass)
                    break;
                default:
                    $matchEl.children('.homeentrant').removeClass(winnerclass)
                    $matchEl.children('.visitorentrant').removeClass(winnerclass)
                    break;
            }
            if( typeof data['advanced'] != 'undefined' && data['advanced'] > 0 ) {
                alert("Reloading");
                window.location.reload();
            }
        }

        function updateStatus( data ) {
            console.log('updateStatus');
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.matchstatus').text(data.status);
            if( typeof data['advanced'] != 'undefined' && data['advanced'] > 0 ) {
                alert("Reloading");
                window.location.reload();
            }
        }

        function updateComments( data ) {
            console.log('updateComments');
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.matchcomments').text(data.comments);
        }

        function findMatch( eventId, bracketNum, roundNum, matchNum ) {
            console.log("findMatch(%d,%d,%d,%d)", eventId, bracketNum, roundNum, matchNum );
            let attFilter = 'td[data-eventid="' + eventId + '"]';
            attFilter += '[data-bracketnum="' + bracketNum + '"]';
            attFilter += '[data-roundnum="' + roundNum + '"]';
            attFilter += '[data-matchnum="' + matchNum + '"]';
            let $matchElem = $(attFilter);
            return $matchElem;
        }

        //Determin if match is locked by looling at the stats description
        //TODO: Need to make this a numeric chack
        function matchIsLocked( obj ) {
            let $parent = $(obj).parents('.item-player');
            if( $parent.children('.matchstatus').text().startsWith('Complete')
             || $parent.children('.matchstatus').text().startsWith('Retire')
             || $parent.children('.matchstatus').text().startsWith('Bye')
             || $parent.children('.matchstatus').text().startsWith('Waiting')) {
                 return true;
             }
             return false;
        }

        //Determine if a match is ready for scoring by looking at status description
        //TODO: Need to make this a numeric chack
        function matchIsReady( obj ) {
            let $parent = $(obj).parents('.item-player');
            if( $parent.children('.matchstatus').text().startsWith('Not started')
              || $parent.children('.matchstatus').text().startsWith('In progress')) {
                  return true;
            }
            return false;
        }
        
        //Get all match data from the element/obj
        //Assumes that obj is descendant of .item-player
        function getMatchData( obj ) {
            let parent = $(obj).parents('.item-player');
            if( parent.length == 0) return {};

            let title = parent.children('.matchtitle').text();
            let home = parent.children('.homeentrant').text().replace("1. ","").replace(/\(.*\)/,'');
            let visitor = parent.children('.visitorentrant').text().replace("2. ","").replace(/\(.*\)/,'');
            let status = parent.children('.matchstatus').text();
            let comments = parent.children('.matchcomments').text();
            // let re = /\((\d+)\,(\d+)\,(\d+)\,(\d+)\)/
            // let found = title.match(re);
            // let eventId = found[1];
            // let bracketNum = found[2];
            // let roundNum = found[3];
            // let matchNum = found[4];
            let eventId = parent.attr('data-eventid');
            let bracketNum = parent.attr('data-bracketnum');
            let roundNum = parent.attr('data-roundnum');
            let matchNum = parent.attr('data-matchnum');

            //NOTE: these jquery objects should always have non-empty arrays of equal length
            let $homeGames = parent.find('input[name=homeGames]');
            let $homeTB    = parent.find('input[name=homeTieBreak]');
            let $visitorGames = parent.find('input[name=visitorGames]');
            let $visitorTB    = parent.find('input[name=visitorTieBreak]');

            
            let $matchStartDate = parent.find('input[name=matchStartDate]');
            let matchStartDate  = $matchStartDate.val();
            let $matchStartTime = parent.find('input[name=matchStartTime]');
            let matchStartTime  = $matchStartTime.val();

            let scores = [];
            for( let i = 1; i <= tennis_draw_obj.numSets; i++ ) {
                if( i == tennis_draw_obj.numSets) {
                    scores.push({"setNum":i,"homeGames": $homeGames[i-1].value,"visitorGames": $visitorGames[i-1].value,"homeTieBreaker": $homeTB.val(), "visitorTieBreaker": $visitorTB.val()});
                }
                else {
                    scores.push({"setNum":i, "homeGames": $homeGames[i-1].value, "visitorGames": $visitorGames[i-1].value, "homeTieBreaker": 0, "visitorTieBreaker": 0});
                }
            }

            let data = {"eventid": eventId, "bracketnum": bracketNum, "roundnum": roundNum, "matchnum": matchNum
                        , "home": home
                        , "visitor": visitor
                        , "status": status
                        , "comments": comments
                        , "matchstartdate": matchStartDate
                        , "matchstarttime": matchStartTime
                        , "score": scores };
            console.log("getMatchData....");
            console.log(data);
            return data;
        }

        /* -------------------- Menu Visibility ------------------------------ */
        //Click on the menu icon to open the menu
        $('.menu-icon').on('click', function ( event ) {
            console.log('show menu....');
            console.log( this );
            console.log( event.target );
            if( tennis_draw_obj.isBracketApproved + 0 > 0) {
                if( $(event.target).hasClass('.menu-icon') ) {
                    $(event.target).children('.matchaction.approved').show();
                }
                else {
                    $(event.target).parents('.menu-icon').find('.matchaction.approved').show();
                }
            } 
            else {
                if( $(event.target ).hasClass('.menu-icon') ) {
                    $(event.target).children('.matchaction.unapproved').show();
                }
                else {
                    $(event.target).parents('.menu-icon').find('.matchaction.unapproved').show();
                }
            }
            event.preventDefault();
        });
        
        //Support clicking away from the menu to close it
        $('body').on('click', function( event ) {
            if( !$(event.target).hasClass('menu-icon') 
             && !$(event.target).parents().hasClass('menu-icon')
             && !$(event.target).hasClass('tablematchscores')) {
                $('.matchaction').hide();
                $('table.tablematchscores').hide();
            }
        });
        
        /* ------------------------------Menu Actions ---------------------------------*/
        //Change the home player/entrant
        // Can only be done if bracket is not yet approved
        $('.changehome').on('click', function (event) {
            console.log("change home");
            console.log(this);
            let matchdata = getMatchData(this);
            let home = prompt("Please enter name of home entrant", matchdata.home);
            if( null == home ) {
                return;
            }
            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "changehome"
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "player": home } );
        });

        //Default the home entrant/player
        $('.defaulthome').on('click', function (event) {
            console.log("default home");
            
            if( !matchIsReady(this)) {
                alert('Match is not ready for scoring.')
                return;
            }
            let matchdata = getMatchData(this);
            let comments = prompt("Please enter reason for defaulting home entrant", matchdata.comments);
            if( null == comments ) {
                return;
            }
            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "defaultentrant"
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "player": 'home'
                    , "comments": comments } );

        });

        //Change the home player/entrant
        // Can only be done if bracket is not yet approved
        $('.changevisitor').on('click', function (event) {
            console.log("change visitor");
            console.log(this);
            let matchdata = getMatchData(this);
            let visitor = prompt("Please enter name visitor entrant", matchdata.visitor);
            if( null == visitor ) {
                return;
            }

            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "changevisitor" 
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "player": visitor } );
        });
        
        //Default the visitor entrant/player
        $('.defaultvisitor').on('click', function (event) {
            console.log("default visitor");
            
            if( !matchIsReady(this)) {
                alert('Match is not read for scoring.')
                return;
            }

            let matchdata = getMatchData(this);
            let comments = prompt("Please enter reason for defaulting visitor entrant", matchdata.comments);
            if( null == comments ) {
                return;
            }
            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "defaultentrant"
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "player": 'visitor'
                    , "comments": comments } );

        });

        //Record the match scores
        $('.recordscore').on('click', function(event) {
            console.log("record score");
            if( !matchIsReady(this)) {
                alert('Match is not ready for scoring.')
                return;
            }
            let $parent = $(this).parents('.item-player');
            $parent.find('.showmatchscores').hide();
            $parent.find('.changematchscores').fadeIn( 500 );

        });

        //Cancel the changing of match scores
        $('.cancelmatchscores').on('click', function( event ) {
            console.log("cancel scores");
            
            let $parent = $(this).parents('.item-player');
            $parent.find('.changematchscores').hide();
            $parent.find('.showmatchscores').fadeIn( 500 );
        });

        //Save the recorded match score
        $('.savematchscores').on('click', function (event) {
            console.log("save scores");
            let matchdata = getMatchData(this);
            let $parent = $(this).parents('.item-player');
            $parent.find('.showmatchscores').hide();
            $parent.find('.changematchscores').fadeOut( 500 );

            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "savescore" 
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "score": matchdata.score } );

        });

        //Capture start date & time of the match
        $('.setmatchstart').on('click', function (event) {
            console.log("match start");
            console.log(this);
            let $parent = $(this).parents('.item-player');
            $parent.find('.matchstart').hide();
            $parent.find('.changematchstart').fadeIn( 500 );
        });
        
        //Cancel the setting of match start date/time
        $('.cancelmatchstart').on('click', function( event ) {
            console.log("cancel scores");
            
            let $parent = $(this).parents('.item-player');
            $parent.find('.changematchstart').hide();
            $parent.find('.matchstart').fadeIn( 500 );
        });

        //Save start date & time of the match
        $('.savematchstart').on('click', function (event) {
            console.log("match start");
            console.log(this);
            let matchdata = getMatchData(this);

            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "setmatchstart"
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "matchstartdate": matchdata.matchstartdate
                    , "matchstarttime": matchdata.matchstarttime } );
        });

        //Capture comments regarding the match
        $('.setcomments').on('click', function (event) {
            console.log("set comments");
            console.log(this);
            let matchdata = getMatchData(this);
            let comments = prompt("Please enter comments", matchdata.comments);
            if( null == comments ) {
                return;
            }

            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "setcomments"
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "comments": comments } );

        });
        
        /* ------------------------Button Actions -------------------------------------- */
        //Approve draw
        $('#approveDraw').on('click', function( event ) {
            console.log("Approve draw fired!");

            $(this).prop('disabled', true);
            let eventId = tennis_draw_obj.eventId;   //$('.bracketdraw').attr('data-eventid');
            let bracketName = tennis_draw_obj.bracketName; //$('.bracketdraw').attr('data-bracketname');

            $(this).prop('disabled', true);
            ajaxFun( {"task": "approve", "eventId": eventId, "bracketName": bracketName } );
        });

        //Remove preliminary rounds
        $('#removePrelim').on('click', function( event ) {
            console.log("Remove preliminary round fired!");
            let ans = confirm("Are you sure?");
            if( ans != true ) return;

            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;

            $(this).prop('disabled', true);
            ajaxFun( {"task": "reset", "eventId": eventId, "bracketName": bracketName} );
        });

        $('.changematchscores').hide();
        $('.showmatchscores').show();  
    });
})(jQuery);
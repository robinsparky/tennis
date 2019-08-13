(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Matches");
        console.log(tennis_draw_obj);

        var longtimeout = 60000;
        var shorttimeout = 5000;

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
        }

        function updateHome( data ) {
            console.log('updateHome');
            console.log( data );
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.homeentrant').text(data.player);
        }

        function updateVisitor( data ) {
            console.log('updateVisitor');
            console.log( data );
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            $matchEl.children('.visitorentrant').text(data.player);
        }

        function updateScore( data ) {
            console.log('updateScore');
            console.log( data );
            let $matchEl = findMatch( data.eventId, data.bracketNum, data.roundNum, data.matchNum );
            console.log( data.score );
            $matchEl.children('.matchscore').empty();
            $matchEl.children('.matchscore').append( data.score );
        }

        function updateComments( data ) {
            console.log('updateComments');
            console.log( data );
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
            //console.log($matchElem);
            return $matchElem;
        }
        
        //Get all match data from the element/obj
        //Assumes that obj is descendant of .item-player
        function getMatchData( obj ) {
            let parent = $(obj).parents('.item-player');
            if( parent.length == 0) return {};

            let title = parent.children('.matchtitle').text();
            let home = parent.children('.homeentrant').text().replace("1. ","").replace(/\(.*\)/,'');
            let visitor = parent.children('.visitorentrant').text().replace("2. ","").replace(/\(.*\)/,'');
            let score = parent.children('.matchscore').text();
            let status = parent.children('.matchstatus').text();
            let comments = parent.children('.matchcomments').text();
            let re = /\((\d+)\,(\d+)\,(\d+)\,(\d+)\)/
            //console.log(title);
            let found = title.match(re);
            //console.log(found);
            let eventId = found[1];
            let bracketNum = found[2];
            let roundNum = found[3];
            let matchNum = found[4];

            let data = {"eventid": eventId, "bracketnum": bracketNum, "roundnum": roundNum, "matchnum": matchNum, "home": home, "visitor": visitor, "score": score, "status": status, "comments": comments}
            console.log("EventId=%d; Bracket Number=%d; Round Number=%d; Match Number=%d; Home=%s; Visitor=%s; Score=%s, Status=%s, Comments=%s"
                       ,data.eventid, data.bracketnum, data.roundnum, data.matchnum, data.home, data.visitor, data.score, data.status, data.comments);
            return data;
        }

        //Approve draw
        $('#approveDraw').on('click', function( event ) {
            console.log("Approve draw fired!");

            $(this).prop('disabled', true);
            let eventId = $('.bracketdraw').attr('data-eventid');
            let bracketName = $('.bracketdraw').attr('data-bracketname');

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

        $('.menu-icon').on('click', function ( event ) {
            console.log('show menu....');
            console.log(event.target);
            if( tennis_draw_obj.isBracketApproved + 0 > 0) {
                if( $(event.target ).hasClass('.menu-icon') ) {
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
            event.preventDefault();
        }
        });
        
        $('body').on('click', function( event ) {
            if( !$(event.target).hasClass('menu-icon') && !$(event.target).parents().hasClass('menu-icon')) {
                $('.matchaction').hide();
            }
        });
            
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

        $('.recordscore').on('click', function (event) {
            console.log("record score");
            console.log(this);
            let matchdata = getMatchData(this);
            let score = prompt("Please enter match score (eg. 6-4, 3-6, 6-6(4)", matchdata.score);
            if( null == score ) {
                return;
            }

            let eventId = tennis_draw_obj.eventId;            
            let bracketName = tennis_draw_obj.bracketName;
            ajaxFun( {"task": "recordscore" 
                    , "eventId": eventId
                    , "bracketNum": matchdata.bracketnum
                    , "roundNum": matchdata.roundnum
                    , "matchNum": matchdata.matchnum
                    , "bracketName": bracketName
                    , "score": score } );

        });

        $('.setcomments').on('click', function (event) {
            console.log("record score");
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

    });
})(jQuery);
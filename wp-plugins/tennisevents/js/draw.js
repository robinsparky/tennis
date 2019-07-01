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
            data = data || [];
            console.log(data);
            let numRounds = $('.bracketdraw').attr('data-numrounds');
            for( let rn = 1; rn <= numRounds + 1; rn++) {
                matches = data.filter( match => {
                                        return rn == match.roundNumber;
                                    });
                if( matches.length < 1 ) break; //quit if nor more data

                matches = matches.sort( function(m1,m2) {
                    //descending sort because 'places' seems to be in reverse order???
                    if( m1.matchNumber < m2.matchNumber) {return 1}
                    if( m1.matchNumber > m2.matchNumber) {return -1}
                    return 0;
                });

                //Get all td's for this round number
                filter = 'td[data-round=' + rn + ']';
                places = $('.bracketdraw ' + filter);

                entrant = null;
                places.each( function(index) {
                    let pos = index + 1;
                    if ( pos % 2 == 0) {
                        //Even Number
                        name = entrant.visitorEntrant;
                    } else {
                        //Odd Number
                        entrant = matches.pop();
                        name = entrant.homeEntrant;
                    }
                    $(this).html(name);
                });
            }
        }

        //Load up the entrants into the draw
        let eventId = $('.bracketdraw').attr('data-eventid');
        let bracketName = $('.bracketdraw').attr('data-bracketname');

        ajaxFun( {"task": "getdata", "eventId": eventId, "bracketName": bracketName } );
    });
})(jQuery);
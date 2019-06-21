
(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Signup");
        console.log(tennis_signupdata_obj);

        var longtimeout = 60000;
        var shorttimeout = 15000;

        var signupDataMask = {task: "", entrantName: "", currentPos: 0, newPos: 0, seed: 0, clubId: 0, eventId: 0 }

        let ajaxFun = function( signupData ) {
            console.log('Signup Management: ajaxFun');
            let reqData =  { 'action': tennis_signupdata_obj.action      
                           , 'security': tennis_signupdata_obj.security 
                           , 'data': signupData };
            console.log("************************Parameters:");
            console.log( reqData );

                // Send Ajax request with data 
            let jqxhr = $.ajax( { url: tennis_signupdata_obj.ajaxurl    
                                , method: "POST"
                                , async: true
                                , data: reqData
                                , dataType: 'json'
                        ,beforeSend: function( jqxhr, settings ) {
                            //Disable the 'Done' button
                            $(sig).html('Loading...');
                        }})
                    .done( function( res, jqxhr ) {
                        console.log("done.res:");
                        console.log(res);
                        if( res.success ) {
                            console.log('Success (res.data):');
                            console.log(res.data);
                            //Do stuff with data...
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
                        console.log("fail");
                        console.log("Error: %s -->%s", textStatus, errorThrown );
                        var errmess = "Error: status='" + textStatus + "--->" + errorThrown;
                        errmess += jqXHR.responseText;
                        console.log('jqXHR:');
                        console.log(jqXHR);
                        $(sig).addClass('care-error');
                        $(sig).html(errmess);
                    })
                    .always( function() {
                        console.log( "always" );
                        setTimeout(function(){
                                    $(sig).html('');
                                    $(sig).removeClass('care-error');
                                    $( ".eventSignup" ).children("li").removeClass('entrantHighlight');
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

        function handleSortStop( event, ui ) {
            let item = ui.item;
            let target = $( event.target );
            //NOTE: this == event.target == ul (droppable)
            $( item )
                .addClass( "entrantHighlight" )
            // console.log("item:")
            // console.log( item.context );
            // console.log("previous sibling:");
            // console.log( item.context.previousSibling);
            // console.log("next sibling:");
            // console.log( item.context.nextSibling);
            let currentPos = parseFloat(item.context.dataset.currentpos);
            let prevPos = parseFloat(item.context.previousSibling.dataset.currentpos);
            let nextPos = parseFloat(item.context.nextSibling.dataset.currentpos);
            let newPos = (prevPos + nextPos ) / 2.0;
            console.log("prevPos=" + prevPos + "; nextPos=" + nextPos + "; moveTo=" + newPos);

            signupData = signupDataMask;
            signupData.task="move";
            signupData.currentPos = currentPos;
            signupData.newPos = newPos;

            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");

            ajaxFun( signupData );
        }


        $( ".eventSignup" ).sortable({
            revert: true,
            cancel: "a,button,input",
            items: "> li",
            containment: 'parent',
            cursor: 'move',
            opacity: 0.7,
            scrollSpeed:10,
            helper: "original",
            stop: handleSortStop
        });

        $( "ul, li" ).disableSelection();

        $(".entrantDelete").on('click', function(e) {
            console.log("#delete entrant fired!");
        });

        $("#addEntrant").on('click', function(e) {
            console.log("#add entrant fired!");
        });

        $("#saveChanges").on('click', function(e) {
            console.log("#save changes fired!");
        });

       $('#care-report-select').change(function(e) {
            console.log("#care-report-select fired!");
            selectionText = e.target.options[e.target.selectedIndex].text;
            selectionId = e.target.value; 
            console.log("id=%s; title='%s'",selectionId, selectionText);
       });

       $('#care_clear_report').on('click', function(e) {
            $('#' + tennis_signupdata_obj.reporttarget).html('');
       });

       $('#care_get_report').on('click', function(e) {
            console.log("#pass_get_report fired!");
            console.log(this);
            row = $(this).closest("tr");
            startDate = row.find("input#care_report_start").val();
            console.log(startDate);
            endDate = row.find("input#care_report_end").val();
            console.log(endDate);
            ajaxFun();
       });

    });
 })(jQuery);
        
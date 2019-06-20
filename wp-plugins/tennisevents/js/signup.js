
(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Signup");
        console.log(tennis_signupdata_obj);

        var longtimeout = 60000;
        var shorttimeout = 15000;

        var iMouseDown  = false;
        var lMouseState = false;
        var dragObject  = null;
        
        var DragDrops   = [];
        var curTarget   = null;
        var lastTarget  = null;
        var rootParent  = null;
        var rootSibling = null;

        let ajaxFun = function( ) {
            console.log('Signup Management: ajaxFun');
            let reqData =  { 'action': tennis_signupdata_obj.action      
                           , 'security': tennis_signupdata_obj.security };
            //console.log( reqData );
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
                            report = generateTable(res.data.returnData,care_pass_mgmt.titles,selectionText,'management-report')
                            $('#' + care_pass_mgmt.reporttarget).append(report);
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
        
        function handleDragStart( event, ui ) {

        }

        function handleDragStop( event, ui ) {
            var offsetXPos = parseInt( ui.offset.left );
            var offsetYPos = parseInt( ui.offset.top );
            console.log( "Drag stopped! Offset: (" + offsetXPos + ", " + offsetYPos + ")");
            console.log(ui);
        }

        function handleDrag( event ) {
            console.log( "......dragging..........");
        }
        
        function handleDropEvent( event, ui ) {
            var draggable = ui.draggable;
            var target = $( event.target );
            console.log( this );
            console.log( ui );
            console.log( event );
            console.log( event.target );
            console.log( 'The entrant with ID "' + draggable.attr('id') + '" was dropped onto me!' );
            console.log( 'I am: ');
            console.log( event.target );
            $( this )
                .addClass( "entrantHighlight" )
        }

        $(".entrantSignup").draggable({
            containment: 'parent',
            cursor: 'move',
            cursorAt: { top: -5, left: -5 },
            snap: '.eventSignup',
            scroll: true,
            scrollSpeed:10,
            scrollSensitivity: 100,
            stack: ".entrantSignup",
            start: handleDragStart,
            stop: handleDragStop,
            drag: handleDrag,
            opacity: 0.7,      
            connectToSortable: ".eventSignup",
            helper: "original",
            revert: "invalid",
            handle: ".entrantPosition"
        });
          
        $('.eventSignup').droppable( {
            drop: handleDropEvent
        } );

        $( ".eventSignup" ).sortable({
            revert: true
        });

        $( "ul, li" ).disableSelection();

        /*
        $(".entrantSignup").mouseenter(function() {
            console.log("You entered entrant!");
        });

        $(".entrantSignup").mouseleave(function( event ) {
            var target = $( event.target );
            if ( target.is( "li" ) ) {
              console.log("You are now leaving entrant - " + event.target.nodeName );
            }
        });
        */

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
        
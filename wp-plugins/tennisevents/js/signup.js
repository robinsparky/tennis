(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Signup");
        console.log(tennis_signupdata_obj);

        var longtimeout = 60000;
        var shorttimeout = 5000;
        var minNameLength = 3;

        var signupDataMask = {task: "", name: "", newName: "", position: 0, newPos: 0, seed: 0, clubId: 0, eventId: 0 }
        var signupData = null;

        let ajaxFun = function( signupData ) {
            console.log('Signup Management: ajaxFun');
            let reqData =  { 'action': tennis_signupdata_obj.action      
                           , 'security': tennis_signupdata_obj.security 
                           , 'data': signupData };
            console.log("Parameters:");
            console.log( reqData );

                // Send Ajax request with data 
            let jqxhr = $.ajax( { url: tennis_signupdata_obj.ajaxurl    
                                , method: "POST"
                                , async: true
                                , data: reqData
                                , dataType: 'json'
                        ,beforeSend: function( jqxhr, settings ) {
                            //Disable the 'Delete and Add' buttons ???
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
                        console.log("fail");
                        console.log("Error: %s -->%s", textStatus, errorThrown );
                        var errmess = "Error: status='" + textStatus + "--->" + errorThrown;
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
                                    $( ".eventSignup" ).children("li").removeClass('entrantHighlight');
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
            ctr = 1;
            for( var i=0; i < data.length; i++ ) {
                let entrant = data[i];
                let key     = "#" + entrant.name.replace(/ /g,'_');
                //console.log(entrant);
                if( $(key).length > 0) {
                    $(key).children(".entrantPosition").html( ctr++ + ".");
                    $(key).attr("data-currentPos",entrant.position);
                    $(key).children("input.entrantName").val(entrant.name);
                    $(key).children("input.entrantSeed").val(entrant.seed);
                }
                else {
                    console.log("Could not find entrant at index=" + i);
                    console.log(entrant);
                }
            }
        }

        function getMaxPosition() {
            let max = 0.0;
            $('.eventSignup').children("li").each( function() {
                if( parseFloat( this.dataset.currentpos ) > max )
                {
                     max = parseFloat( this.dataset.currentpos );
                }
            });
            return max;
        }

        //Move an entrant
        function handleSortStop( event, ui ) {
            let item = ui.item;
            let target = $( event.target );
            //NOTE: this == event.target == ul (droppable)
            $( item ).addClass( "entrantHighlight" );

            let name = $(item.context).attr("id").replace(/_/g, ' ');
            
            let maxPos = getMaxPosition();
            console.log("Maximum position is %d", maxPos );

            let currentPos = parseFloat(item.context.dataset.currentpos);

            let prevPos = 0;
            if(item.context.previousSibling) {
                prevPos = parseFloat(item.context.previousSibling.dataset.currentpos);
            }
            let nextPos = 0;
            if( item.context.nextSibling ) {
                nextPos = parseFloat(item.context.nextSibling.dataset.currentpos);
            }

            let newPos = (prevPos + nextPos ) / 2.0;
            if( prevPos === 0 ) {
                newPos = nextPos - 0.5;
            }
            else if( nextPos === 0 ) {
                newPos = maxPos + 1;
            }
            console.log("prevPos=" + prevPos + "; nextPos=" + nextPos + "; moveTo=" + newPos);

            $(item.context).attr("data-currentpos", newPos );
            $(item.context).children('.entrantPosition').html(newPos);

            signupData = signupDataMask;
            signupData.task="move";
            signupData.name = name;
            signupData.currentPos = currentPos;
            signupData.newPos = newPos;

            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");

            ajaxFun( signupData );
        }
        
        //Make ths list of entrants sortable
        $( ".eventSignup" ).sortable({
            revert: true,
            handle: ".entrantPosition",
            cancel: "a,button,input",
            containment: 'parent',
            cursor: 'move',
            opacity: 0.7,
            scrollSpeed:10,
            helper: "original",
            stop: handleSortStop
        });

        $('#tennis-event-message').draggable();
                
        //Modify Seed
        $(".eventSignup").on("change", "input.entrantSeed", function(e) {
            console.log('Seed change fired!');
            entrantId = $(this).parent("li.entrantSignup").attr("id");
            console.log("entrantId=%s", entrantId);
            signupData = signupDataMask;
            signupData.task="update";
            signupData.seed = $(this).val();
            if(signupData.seed < 0 || signupData.seed > 100 ) return;

            signupData.newName = '';
            console.log("New seed value is %d", signupData.seed);
            signupData.name = entrantId.replace(/_/g, ' ');
            signupData.position = $(this).parent("li.entrantSignup").attr('data-currentPos');

            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");
            ajaxFun( signupData );
        });
        
        //Modify Name
        $(".eventSignup").on("change", "input.entrantName", function(e) {
            entrantId = $(this).parent("li.entrantSignup").attr("id");
            console.log("Name change fired for entrantId=%s", entrantId);
            signupData = signupDataMask;
            signupData.task="update";
            signupData.name = entrantId.replace(/_/g, ' ');
            signupData.newName = $(this).val();
            if( signupData.newName.length < minNameLength ) {
                $(this).val( signupData.name );
                return;
            }
            //Change id to match the new name so it can be found using the new name
            $(this).parent("li.entrantSignup").attr("id", signupData.newName.replace(/ /g, '_'));
            //Make sure we don't change the seed
            signupData.seed = -99;
            console.log("New name value is '%s'", signupData.newName);
            signupData.position = $(this).parent("li.entrantSignup").attr('data-currentPos');

            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");
            ajaxFun( signupData );

        });

        //Delete an entrant
        $(".eventSignup").on('click','button.entrantDelete', function(e) {
            console.log('Delete fired!');
            entrantId = $(this).parent("li.entrantSignup").attr("id");
            console.log("entrantId=%s", entrantId);
            signupData = signupDataMask;
            signupData.name = entrantId.replace(/_/g, ' ');
            signupData.task="delete";

            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");
            
            $(this).parent("li.entrantSignup").hide();

            ajaxFun( signupData );
        });

        //Add an entrant
        $('#addEntrant').on('click', function(e) {
            console.log("Add entrant fired!");
            let name = prompt("Entrant's name: ", "");
            if( null === name) return;
            if( name.length < minNameLength ) return;

            let pos = $('.eventSignup').children().length + 1;
            signupData = signupDataMask;
            signupData.name = name;
            signupData.seed = 0;
            signupData.position = pos;
            signupData.task = "add";
            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");

            liNode = $('<li>',{ id: name.replace(/ /g, '_')
                            ,class:"entrantSignup sortable-container ui-state-default"
                            });
            posDiv = $('<div>', {class: "entrantPosition ui-sortable-handle"
                                ,html: pos + '.'});
            nameInput = $('<input>', {name: "entrantName"
                                     ,type: "text" 
                                     ,maxlength:"35" 
                                     ,size: "15" 
                                     ,class:"entrantName" 
                                     ,value: signupData.name });
            seedInput = $('<input>', {name: "entrantSeed"
                                    ,type: "number" 
                                    ,maxlength:"2" 
                                    ,size: "2" 
                                    ,class:"entrantSeed" 
                                    ,step: "any"
                                    ,value: signupData.seed });
            deleteButton = $('<button>',{class:"button entrantDelete" 
                                        ,type:"button"
                                        ,id: "deleteEntrant"
                                        ,html: "Delete"});
            liNode.append(posDiv);
            liNode.append(nameInput);
            liNode.append(seedInput);
            liNode.append(deleteButton);
            $('.eventSignup').append(liNode);

            ajaxFun( signupData );
            
        });

        //Approve signup
        $('#approveSignup').on('click', function( event ) {
            console.log("Approve signup fired!");
            signupData = signupDataMask;
            signupData.task = "approve";
            signupData.clubId = $('.signupContainer').attr("data-clubid");
            signupData.eventId = $('.signupContainer').attr("data-eventid");

            $(this).prop('disabled', true);
            
            ajaxFun( signupData );
        });

    });
 })(jQuery);
        
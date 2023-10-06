(function($) {   
 
    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Brackets");
        console.log(tennis_bracket_obj);

        var longtimeout = 60000;
        var shorttimeout = 5000;

        const isString = str => ((typeof str === 'string') || (str instanceof String));
        const myTrim = str => str.replace(/^\s+|\s+$/gm,'');

        /**
         * Function to make ajax calls.
         * Needs the "action" and "security" nonce from the local object emitted from the server
         * @param {} matchData 
         */
        let ajaxFun = function( bracketData ) {
            console.log('Bracket Management: ajaxFun');
            let reqData =  { 'action': tennis_bracket_obj.action      
                           , 'security': tennis_bracket_obj.security 
                           , 'data': bracketData };    
            console.log("Parameters:");
            console.log( reqData );

            // Send Ajax request with data 
            let jqxhr = $.ajax( { url: tennis_bracket_obj.ajaxurl    
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

        /**
         * Apply the response from an ajax call to the elements of the page
         * For example, recording scores or defaulting a player or adding comments
         * @param {} data 
         */
        function applyResults( data ) {
            data = data || [];
            let task = "";
            console.log("Apply results:");
            console.log( data );
            if( $.isArray(data) ) {
                console.log("Data is an array");
                task = data['task'];
                console.log(`---------Task is ${task}----------`)
            }
            else if( isString( data )) {
                console.log("Data is a string ... so doing nothing!");
                return;
            }
            else {
                console.log("Data is an object");
                task = data.task;
                console.log(`---------Task is ${task}----------`)
            }
            switch(task) {
                case 'editname':
                    updateBracketName( data );
                    break;
                case 'addbracket':
                    addBracket( data );
                    break;
                case 'preparenextmonth':
                    reloadWindow( data );
                    break;
                case 'modifygender':
                    updateGenderType( data )
                    break;
                case 'modifyminage':
                    updateMinAge( data )
                    break;
                case 'modifymaxage':
                    updateMaxAge( data )
                    break;
                case 'modifymatchtype':
                    updateMatchType( data )
                    break;
                case 'modifyformat':
                    updateEventFormat( data )
                    break;
                case 'modifyscorerule':
                    updateScoreType( data )
                    break;
                case 'modifysignupby':
                    updateSignupBy( data )
                    break;
                case 'modifystartdate':
                    updateStartDate( data )
                    break;
                case 'modifyenddate':
                    updateEndDate( data )
                    break;
                default:
                    console.log("Unknown task from server: '%s'", task);
                    break;
            }
        }

        /**
         * Reload this window
         * @param {*} data 
         */
        function reloadWindow( data ) {
            console.log('reloadWindow');
            window.location.reload(); 
        }

        /**
         * Update the bracket name
         * @param {*} data 
         */
        function updateBracketName( data ) {
            console.log('updateBracketBracket');
            let $matchEl = findBracket( data.eventId, data.bracketNum );
            $matchEl.children('.bracket-name').text(data.bracketName);
            $matchEl.children('.bracket-signup-link').attr("href",data.signuplink);
            $matchEl.children('.bracket-draw-link').attr("href",data.drawlink);
        }

        /**
         * Add a new bracket
         * @param {*} data 
         */
        function addBracket( data ) {
            console.log('addBracket');
            console.log(data)
            sel = `.tennis-event-brackets[data-eventid="${data.eventId}"]`;
            $parent = $(sel);

            let templ = `<li class="item-bracket" data-eventid="${data.eventId}" data-bracketnum="${data.bracketNum}">
            <span class="bracket-name" contenteditable="true">${data.bracketName}</span>&colon;
            <a class="bracket-signup-link" href="${data.signuplink}">Signup, </a>
            <a class="bracket-draw-link" href="${data.drawlink}">Draw</a>
            <img class="remove-bracket" src="${data.imgsrc}">
            `;

            $parent.append(templ)
            $el = findBracket(data.eventId, data.bracketNum)
            $el.children('span.bracket-name').on('change', onChange)
                .on('focus', onFocus)
                .on('blur', onBlur);
            $('.tennis-add-bracket').prop('disabled', false );
            enableDeleteButton();
        }

        /**
         * Hide a removed bracket
         * @param {*} data 
         */
        function hideBracket( data ) {
            let eventId = data['eventId']
            let bracketNum = data['bracketNum']
            let $el = findBracket(eventId, bracketNum)
            $el.hide();
        }

        /**
         * Find the match element on the page using its composite identifier.
         * It is very important that this find method is based on event/bracket identifiers and not on css class or element id.
         * @param int eventId 
         * @param int bracketNum 
         * @return jquery object
         */
        function findBracket( eventId, bracketNum ) {
            console.log("findMatch(%d,%d)", eventId, bracketNum );
            let attFilter = '.item-bracket[data-eventid="' + eventId + '"]';
            attFilter += '[data-bracketnum="' + bracketNum + '"]';
            let $bracketElem = $(attFilter);
            return $bracketElem;
        }
        
        /**
         * Get all bracket data from the element/obj
         * @param element el Assumes that el is descendant of .item-bracket
         */
        function getBracketData( el ) {
            let parent = $(el).parents('.item-bracket');
            if( parent.length == 0) return {};

            let eventId = parent.attr('data-eventid');
            let bracketNum = parent.attr('data-bracketnum');

            let $bracketName = parent.find('span.bracket-name');
            let bracketName  = myTrim($bracketName.text());

            let data = {"eventid": eventId, "bracketnum": bracketNum
                        , "bracketName": bracketName };
            console.log("getBracketData....");
            console.log(data);
            return data;
        }

        function getOwningEvent( el ) {
            console.log("getOwningEvent....");
            let parent = $(el).parents('.tennis-parent-event');
            if( parent.length == 0) return {};
            let eventId = parent.attr("data-event-id");
            let postId = parent.attr("id");
            let data = {"eventId": eventId, "postId": postId }
            console.log(data);
            return data;
        }

        function uniqueName( eventId ) {
            let sel = `.tennis-event-brackets[data-eventid="${eventId}"]`;
            let $parent = $(sel);
            let existingNames = [];
            let newName = '';
            $parent.find('span.bracket-name').each( function(idx) {
                existingNames.push(this.innerText)} );
            for( num=1; num<100; num++ ) {
                newName = `Bracket${num}`;
                if(existingNames.every( str => str !== newName  )) {
                    break;
                }
            }            
            return newName;
        }

        /**
         * Get all of the attributes of a leaf event. i.e. An event to which brackets are attached.
         * @param {*} el 
         * @returns Object with properties of a leaf event
         */
        function getLeafEventData( el ) {
            let $parent = $(el).closest('.tennis-event-meta');
            if( $parent.length == 0) return {};
            
            let evtId = $parent.attr('id')
            let postId = $parent.attr('data-postid');
            let gender = $parent.find('.gender_selector option:selected').val();
            let minAge = $parent.find('.min_age_input').val();
            let maxAge = $parent.find('.max_age_input').val();
            let signupBy = $parent.find('.signup_by_input').val();
            let startDate = $parent.find('.start_date_input').val();
            let endDate = $parent.find('.end_date_input').val();
            let format = $parent.find('.format_selector option:selected').val();
            let scoreRule = $parent.find('.score_rules_selector option:selected').val();
            let matchType = $parent.find('.match_type_selector option:selected').val();
            let data = {"eventId":evtId
                        ,"postId": postId
                        ,"gender": gender
                        ,"minAge": minAge
                        ,"maxAge": maxAge
                        ,"signupBy": signupBy
                        ,"startDate": startDate
                        ,"endDate": endDate
                        ,"format": format
                        ,"matchType": matchType
                        ,"scoreRule": scoreRule
                        }
            console.log(data)
            return data;
        }

        //Update the min age
        function updateMinAge( data ) {
            console.log('updateMinAge')
            console.log(data)

        }

        //Update the max age
        function updateMaxAge( data ) {
            console.log('updateMaxAge')
            console.log(data)
            $parent = $(`table#${data.eventId}`)
            $el = $parent.find('.max_age_input')
            origVal = $el.attr('data-origval')
            console.log(`updateMaxAge orig val=${origVal}`)
        }

        //Update the Gender Type
        function updateGenderType( data ) {
            console.log('updateGenderType')
            console.log(data)

        }

        //Update the Event Format
        function updateEventFormat( data ) {
            console.log('updateEventFormat')
            console.log(data)

        }

        //Update the match type
        function updateMatchType( data ) {
            console.log('updateMatchType')
            console.log(data)

        }

        //Update the date fields depending on the value of signBy
        function updateSignupBy( data ) {
            console.log('updateSignupBy')
            console.log(data)
        }

        //Update the date fields depending on the value of startDate
        function updateStartDate( data ) {
            console.log('updateStartDate')
            console.log(data) 
        }

        //Update the date fields depending on the value of endDate
        function updateEndDate( data ) {
            console.log('updateEndDate')
            console.log(data)            
        }

        function updateScoreType( data ) {
            console.log('updateScoreType')
            console.log(data)
        }

        function enableDeleteButton(){
            $('.remove-bracket').hover( function(event) {
                $(this).css('cursor','pointer');
            }, function(event) {
                $(this).css('cursor','default');
            });
        }
        
        /**
         * Change the name of the bracket
         */
        const onChange = function( event ) {
            let bracketdata = getBracketData(event.target);
            let bracketName = event.target.innerText || '';
            if( bracketName === '') return;

            let oldBracketName = $(event.target).data('beforeContentEdit');
            $(event.target).removeData('beforeContentEdit')
            //let eventId = tennis_bracket_obj.eventId; 
            let config =  {"task": "editname"
                            , "eventId": bracketdata.eventid
                            , "bracketNum": bracketdata.bracketnum
                            , "bracketName": bracketName
                            , "oldBracketName": oldBracketName }
            console.log(config)
            ajaxFun( config );
        }

        /**
         * Callback for focus event on the name of the bracket
         * @param {*} event 
         */
        const onFocus = function( event ) {
            console.log(event.target.innerText);
            $(event.target).data('beforeContentEdit', event.target.innerText);
            //$(this).attr('data-beforeContentEdit',this.innerText);
        }

        /**
         * Callback function for the blur event of the name of the bracket
         * @param {*} event 
         */
        const onBlur = function( event ) {
            if ($(event.target).data('beforeContentEdit') !== event.target.innerText) {
                $(event.target).trigger('change');
            }
        }

        //Change Score Rule
        const onChangeScoreRule = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`score rules change detected: ${newVal} with postIt=${postIt}`)
            const cssrule1 = "div.scoreruleslist";
            const $siblingList = $(event.target).siblings(cssrule1)
            $siblingList.children().css("display","none")
            const cssrule2 = `ul.${newVal}`;
            const $childRule = $siblingList.children(cssrule2)
            $childRule.css('display','block')
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifyscorerule"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "scoreType": newVal } );
            }
        }
        
        //Change Gender
        const onChangeGender = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`gender change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifygender"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "gender": newVal } );
            }
        }
        
        //Change Gender
        const onChangeMatchType = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Match type change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifymatchtype"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "matchType": newVal } );
            }
        }

        //Change Format
        const onChangeFormat = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Format change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifyformat"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "eventFormat": newVal } );
            }
        }

        //Change Min Age
        const onChangeMinAge = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Min Age change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifyminage"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "minAge": newVal } );
            }
        }

        //Change Max Age
        const onChangeMaxAge = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Max Age change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifymaxage"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "maxAge": newVal } );
            }
        }

        //Change Signup By date
        const onChangeSignupBy = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Signup By change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifysignupby"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "startDate": data.startDate
                        , "endDate": data.endDate
                        , "signupBy": newVal } );
            }
        }
        
        //Change Start Date
        const onChangeStartDate = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Start Date change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifystartdate"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "signupBy": data.signupBy
                        , "endDate": data.endDate
                        , "startDate": newVal } );
            }
        }

        //Change End Date
        const onChangeEndDate = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`End Date change detected: ${newVal} with post=${postIt}`)
            let data = getLeafEventData(event.target)
            if(postIt === true) {
                console.log("Posting change!")
                ajaxFun( {"task": "modifyenddate"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "signupBy": data.signupBy
                        , "startDate": data.startDate
                        , "endDate": newVal } );
            }
        }

        /**
        * ----------------------------------User Actions ------------------------------------------------------
        */
         $('span.bracket-name').on('change', onChange);
         $('span.bracket-name').on('focus', onFocus);
         $('span.bracket-name').on('blur', onBlur);
        
         //OnChange the Gender
        $(".gender_selector").on("change", function(event) {
            onChangeGender(event);
        });

        //On Change the Match Type     
        $(".match_type_selector").on("change", function(event) {
            onChangeMatchType(event);
        });

        //On Change the Format      
        $(".format_selector").on("change", function(event) {
            onChangeFormat(event);
        });

        //On Change the Min Age      
        $(".min_age_input").on("change", function(event) {
            onChangeMinAge(event);
        });

        //On Change the Max Age     
        $(".max_age_input").on("change", function(event) {
            onChangeMaxAge(event);
        });
        
        //On Change the Sign Up By date      
        $(".signup_by_input").on("change", function(event) {
            onChangeSignupBy(event);
        });

        //On Change the Start Date      
        $(".start_date_input").on("change", function(event) {
             onChangeStartDate(event);
        });
 
        //On Change the End Date    
        $(".end_date_input").on("change", function(event) {
              onChangeEndDate(event);
        });
        
        //On Change the score type 
        $(".score_rules_selector").on("change", function(event) {
            onChangeScoreRule(event);
        });

        //On Add a new bracket
        $('.tennis-add-bracket').on('click', function (event) {
            console.log("add bracket");
            console.log(event.target);
            $(this).prop('disabled', true );
            let eventId = event.target.getAttribute("data-eventid");
            // let bracketName = prompt("Please enter name of bracket.",eventId);
            // if( null == bracketName ) {
            //     return;
            // }
            let newName = uniqueName(eventId);

            ajaxFun( {"task": "addbracket"
                    , "eventId": eventId
                    , "bracketName": newName } );
        });

        //On Remove a bracket
        $('.tennis-event-brackets').on('click', '.remove-bracket', function (event) {
            console.log("remove bracket");
            console.log(this);
            let bracketdata = getBracketData(this);
            $(this).removeData('beforeContentEdit')
            //let eventId = tennis_bracket_obj.eventId; 
            if(confirm("Are you sure you want to delete this bracket?")) {
                let config =  {"task": "removebracket"
                            , "eventId": bracketdata.eventid
                            , "bracketNum": bracketdata.bracketnum
                            , "bracketName": bracketdata.bracketName }
                console.log(config)
                ajaxFun( config );
            }
        });
        
        //On click to Copy an event
         $('.tennis-parent-event').on('click', '.tennis-ladder-next-month', function (event) {
            console.log("copy event");
            console.log(this);
            let eventdata = getOwningEvent(this);
            if(confirm("Are you sure you want to copy this event?")) {
                let config =  {"task": "preparenextmonth"
                            , "eventId": eventdata.eventId }
                console.log(config)
                ajaxFun( config );
            }
        });

        /**
         * ------------------------------One time set up actions----------------------------------------------------
         */

        //Enable the delete button
        enableDeleteButton();

        //Show the details of the current Score Type
        const $fixedScoreRules =  $('.score_rules_text');
        $.each($fixedScoreRules, function(index, obj) {
            let evt = new Object;
            evt.target=obj;
            evt.target.value = obj.dataset.scoretype;
            onChangeScoreRule(evt, false)
        })
        
        //Show the details of the currently selected Score Type
        const $selectedScoreRules = $('.score_rules_selector option:selected')
        $.each($selectedScoreRules, function(index, obj) {
            let evt = new Object;
            evt.target=obj.parentElement;
            evt.target.value = obj.value;
            onChangeScoreRule(evt, false)
        })
        

        /**
         * -----------------------The following creates JQuery tabs based on parent events------------------------------
         */
        let $parentEvents = $('.tennis-parent-event');
        $("#tabs").prepend(`<ul class="tennis-event-tabs"></ul>`);
        $parentEvents.each( function(idx, el ) {
            let title = $(this).children('h3').text();
            let id = $(this).attr("id");
            $( "#tabs > ul" ).append( `<li class="tennis-tab-name"><a href="#${id}">${title}</a></li>` );
        } )

        $( ".tennis-event-tabs-container" ).tabs( {active: false, activate: function( event, ui ) {
            ui.newTab.css({'border-bottom-width':'0', 'background-color':'white', 'color': 'black'});
            ui.newTab.children('a').css({'color': 'black'})
            ui.oldTab.css({'border-bottom-width':'1px', 'background-color':'gray', 'color': 'white'});
            ui.oldTab.children('a').css({'color': 'white'})
        }})
        
        // Setter
        $( ".tennis-event-tabs-container" ).tabs( "option", "collapsible", true );
        $( ".tennis-event-tabs-container" ).tabs( "option", "active", false );
        $( ".tennis-event-tabs-container" ).tabs( "option", "event", "click" );
        $( ".tennis-event-tabs-container" ).tabs( "option", "hide", { effect: "fold", duration: 1000 } );
        $( ".tennis-event-tabs-container" ).tabs( "option", "show", { effect: "blind", duration: 1000 } );

        //Classes
        $('.tennis-event-tabs-container').tabs({"ui-tabs-nav": "tennis-event-tabs", "ui-tabs-tab": "tennis-tab-name ui-corner-all"
                                                });
        
    });
})(jQuery);
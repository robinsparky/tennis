(function($) {   
 
    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Events");
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
        let ajaxFun = function( eventData ) {
            console.log('Event Management: ajaxFun');
            let reqData =  { 'action': tennis_bracket_obj.action      
                           , 'security': tennis_bracket_obj.security 
                           , 'data': eventData };    
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
                        console.log("Done (res)");
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
                            console.log(jqxhr)
                            var entiremess = res.data.message + " ...<br/>";
                            for(var i=0; i < res.data.exception.errors.length; i++) {
                                entiremess += res.data.exception.errors[i][0] + '<br/>';
                            }
                            $(sig).addClass('tennis-error');
                            $(sig).html(entiremess);
                        }
                    })
                    .fail( function( jqXHR, textStatus, errorThrown ) {
                        console.log("Failed: %s-->%s", textStatus, errorThrown );
                        var errmess = "Failed: status='" + textStatus + "--->" + errorThrown;
                        console.log("response text:%s",jqXHR.responseText)
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
            if( Array.isArray(data) ) {
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
                case 'addleafevent':
                    reloadWindow( data )
                    break;
                case 'deleteleafevent':
                    break;
                case 'addrootevent':
                    reloadWindow( data )
                    break;
                case 'editrootevent':
                    //reloadWindow( data )
                    updateRootEventTitle( data )
                    updateRootStartDate( data )
                    updateRootEndDate( data )
                    break;
                case 'deleterootevent':
                    reloadWindow( data )
                    break;
                case 'editname':
                    updateBracketName( data )
                    break;
                case 'addbracket':
                    addBracket( data )
                    break;
                case 'removebracket':
                    break;
                case 'preparenextmonth':
                    reloadWindow( data )
                    break;
                case 'modifyeventtitle':
                    updateEventTitle( data )
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
         * Remove html/xml tags from string
         * @param {string} str 
         * @returns 
         */
        function removeTags(str) { 
            if ((str===null) || (str==='')) 
                return false; 
            else
                str = str.toString(); 
                
            // Regular expression to identify HTML tags in 
            // the input string. Replacing the identified 
            // HTML tag with a null string. 
            return str.replace( /(<([^>]+)>)/ig, ''); 
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
            $el.children('span.bracket-name').on('change', onChangeBracketName).on('focus', onFocus).on('blur', onBlur);
            $('.tennis-add-bracket').prop('disabled', false );
            enableDeleteBracketButton();
        }

        /**
         * Hide a removed bracket
         * @param  eventId
         * @param bracketNum
         */
        function hideBracket( eventId, bracketNum ) {            
            console.log("hideBracket(%d,%d)", eventId, bracketNum );
            let $el = findBracket(eventId, bracketNum)
            console.log($el)
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
            console.log("findBracket(%d,%d)", eventId, bracketNum );
            let sel = `.item-bracket[data-eventid="${eventId}"][data-bracketnum="${bracketNum}"]`
            let $bracketElem = $(sel);
            return $bracketElem;
        }
        
        /**
         * Get all bracket data from the element/obj
         * @param element el Assumes that el is a child of .item-bracket
         */
        function getBracketData( el ) {
            console.log('getBracketData')
            let parent = $(el).parents('.item-bracket');
            if( parent.length == 0) return {};

            let eventId = parent.attr('data-eventid');
            let bracketNum = parent.attr('data-bracketnum');

            let $bracketName = parent.find('span.bracket-name');
            let bracketName  = myTrim($bracketName.text());

            let data = {"eventid": eventId, "bracketnum": bracketNum, "bracketName": bracketName };
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

        function uniqueBracketName( eventId ) {
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

        function updateEventTitle( data ) {
            console.log('updateEventTitle')
            console.log(data)
            let $titleEl = $(`.tennis-leaf-event-title[data-eventid='${data['eventId']}'`)
            console.log($titleEl)
            $titleEl.text(data['newTitle'])
            $titleEl.removeData('oldTitle')
        }

        function updateRootEventTitle( data ) {
            console.log('updateRootEventTitle')
            console.log(data)
            const sel = `li > a[href='#${data.postId}']`
            console.log(sel)
            let $titleEl =$(sel)
            //let $titleEl = $(`.tennis-parent-event[data-eventid='${data['eventId']}'`)
            console.log($titleEl)
            $titleEl.text(data.title)
        }

        function updateRootStartDate( data ) {
            console.log('updateRootStartDate')
            console.log(data)
            const sel = `.tennis-parent-event[data-event-id='${data.eventId}'] li.tennis-root-event-start > span`
            console.log(sel)
            let $startEl = $(sel)
            console.log($startEl)
            $startEl.text(data.startDate)
        }

        function updateRootEndDate( data ) {
            console.log('updateRootEndDate')
            console.log(data)
            const sel = `.tennis-parent-event[data-event-id='${data.eventId}'] li.tennis-root-event-end > span`
            console.log(sel)
            let $endEl = $(sel)
            console.log($endEl)
            $endEl.text(data.endDate)
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

        function enableDeleteBracketButton(){
            $('.remove-bracket').on("mouseenter", function(event) {
                $(this).css('cursor','pointer');
            }).on("mouseleave", function(event) {
                $(this).css('cursor','default');
            });
        }
        
        /**
         * Change the name of the bracket
         */
        const onChangeBracketName = function( event ) {
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
         * Change the title of the tournament
         */
        const onChangeTitle = function( event ) {
            console.log("onChangeTitle fired!")
            console.log(event.target)

            const eventId = $(this).attr("data-eventid");
            const postId = $(this).attr("data-postid");
            let eventTitle = (event.target.innerText || '').trim();
            //if( eventTitle === '') return;

            let oldTitle = $(event.target).data('beforeContentEdit').trim();
            $(event.target).removeData('beforeContentEdit')
            let config =  {"task": "modifyeventtitle"
                            , "eventId": eventId
                            , "postId": postId
                            , "newTitle": eventTitle
                            , "oldTitle": oldTitle }
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
            $(this).attr('data-beforeContentEdit',this.innerText);
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
                ajaxFun( {"task": "modifyenddate"
                        , "eventId": data.eventId
                        , "postId": data.postId
                        , "signupBy": data.signupBy
                        , "startDate": data.startDate
                        , "endDate": newVal } );
            }
        }
        
        //Extract the data from the new event form
        function getDataFromForm($form) {
            const title = $form.find("input[name='title']").val()
            const parentId = $form.find("input[name='parentEventId']").val()
            const signupBy = $form.find("input[name='signupby']").val()
            const startDate = $form.find("input[name='startdate']").val()
            const endDate = $form.find("input[name='enddate']").val()
            const gender= $form.find("select[name='GenderTypesNew']").val()
            const matchType= $form.find("select[name='MatchTypesNew']").val()
            const format= $form.find("select[name='AllFormatsNew']").val()
            const scoreType= $form.find("select[name='ScoreRulesNew']").val()
            return {"parentId": parentId,"title": title
                    ,"signupBy": signupBy
                    ,"startDate": startDate
                    ,"endDate": endDate
                    ,"gender": gender
                    ,"matchType": matchType
                    ,"format": format
                    ,"scoreType": scoreType
                }
        }

        /**
        * ----------------------------------User Actions ------------------------------------------------------
        */
         //Bracket name
         $('span.bracket-name').on('change', onChangeBracketName);
         $('span.bracket-name').on('focus', onFocus);
         $('span.bracket-name').on('blur', onBlur);

         //Event title
         $('.tennis-leaf-event-title[contenteditable]').on('change', onChangeTitle);
         $('.tennis-leaf-event-title[contenteditable]').on('focus', onFocus);
         $('.tennis-leaf-event-title[contenteditable]').on('blur', onBlur);
        
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

        /**
         * ----------------------Add Leaf Event aka Tournament-------------------------------------
         */
        //On add a new leaf event dialog
        $('a.tennis-add-event.leaf').on('click', (event) => {
            console.log("Add leaf event");
            const parentId = event.target.dataset.parentid
            const selector = `dialog.tennis-add-event-dialog.leaf[data-parentId='${parentId}']`
            console.log(selector)
            $dialog = $(selector)
            console.log($dialog)
            $dialog.get(0).showModal()
        });

        //Submit or cancel the leaf event dialog
        $('button.tennis-add-event-close.leaf').on('click', (event) => {
            console.log("Submit or cancel add leaf event dialog");
            console.log(event.target)
            $dialog = $(event.target).closest('dialog')
            $dialog.attr('eventadd', $(event.target).val())
            console.log($dialog)
            $dialog.get(0).close()
        });

        //Take action when leaf dialog is closed
        $('dialog.tennis-add-event-dialog.leaf').on('close', (event) => {
            console.log("Add leaf event dialog closed");
            console.log(event.target);
            if($(event.target).attr('eventadd') === 'submitted') {
                console.log('Dialog submitted')
                $form = $(event.target).children('.tennis-add-event-form.leaf')
                console.log($form)
                let allData = getDataFromForm($form)
                allData.task = "addleafevent"
                console.log(allData)
                ajaxFun( allData );
            }
            else {
                console.log("Dialog cancelled")
            }
        });

        /**
         * ----------------------Delete a Leaf Event aka Tournament-------------------------------------
         */
        //On delete leaf event
        $('a.tennis-delete-event.leaf').on('click', (event) => {
            console.log("Delete leaf event");
            const eventId = event.target.dataset.eventid
            console.log("EventId=%d",eventId)
            if(confirm("Are you sure you want to delete this tournament?")) {
                $(event.target).closest('section.tennis-leaf-event').hide();
                ajaxFun(  {"task": 'deleteleafevent', "eventId": eventId } );
            }
        });
        
        /**
         * ----------------------Edit Root Event-------------------------------------
         */
        //On edit a root event dialog
        $('a.tennis-edit-event.root').on('click', (event) => {
            console.log("Edit root event");
            let eventId = $(event.target).attr('data-eventid')
            console.log("EventId=%d",eventId)
            const selector = `dialog.tennis-edit-event-dialog.root[data-eventid='${eventId}']`
            let $dialog = $(selector)
            $dialog = $(selector)
            $dialog.get(0).showModal()
        });

        //Submit or cancel edit root event dialog
        $('button.tennis-edit-event-close.root').on('click', (event) => {
            console.log("Submit or cancel edit root event dialog");
            console.log(event.target)
            $dialog = $(event.target).closest('dialog')
            $dialog.attr('eventedit', $(event.target).val())
            console.log($dialog)
            $dialog.get(0).close()
        });
        
        //Take action when edit root dialog is closed
        $('dialog.tennis-edit-event-dialog.root').on('close', (event) => {
            console.log("Edit root event dialog closed");
            console.log(event.target);
            if($(event.target).attr('eventedit') === 'submitted') {
                console.log('Edit Dialog submitted')
                $form = $(event.target).children('.tennis-edit-event-form.root')
                console.log($form)            
                const eventId = $form.find("input[name='eventId']").val()
                const postId = $form.find("input[name='postId']").val()
                const title = $form.find("input[name='title']").val()
                const startDate = $form.find("input[name='startdate']").val()
                const endDate = $form.find("input[name='enddate']").val()
                let data = {"eventId": eventId
                            ,"postId": postId
                            ,"title": title
                            ,"startDate": startDate
                            ,"endDate": endDate
                        }
                data.task = "editrootevent"
                console.log(data)
                ajaxFun( data );
            }
            else {
                console.log("Dialog cancelled")
            }
        });

        /**
         * ----------------------Add Root Event------------------------------------
         */
        //On add a new root event dialog
        $('button.tennis-add-event.root').on('click', (event) => {
            console.log("Add root event");
            const selector = `dialog.tennis-add-event-dialog.root`
            console.log(selector)
            $dialog = $(selector)
            console.log($dialog)
            $dialog.get(0).showModal()
        });

        //Submit or cancel the add a root event dialog
        $('button.tennis-add-event-close.root').on('click', (event) => {
            console.log("Submit or cancel add root event dialog");
            console.log(event.target)
            $dialog = $(event.target).closest('dialog')
            $dialog.attr('eventadd', $(event.target).val())
            console.log($dialog)
            $dialog.get(0).close()
        });

        //Take action when root dialog is closed
        $('dialog.tennis-add-event-dialog.root').on('close', (event) => {
            console.log("Add root event dialog closed");
            console.log(event.target);
            if($(event.target).attr('eventadd') === 'submitted') {
                console.log('Dialog submitted')
                $form = $(event.target).children('.tennis-add-event-form.root')
                console.log($form)
                const title = $form.find("input[name='title']").val()
                const startDate = $form.find("input[name='startdate']").val()
                const endDate = $form.find("input[name='enddate']").val()
                let data = {"title": title
                            ,"startDate": startDate
                            ,"endDate": endDate
                        }
                data.task = "addrootevent"
                console.log(data)
                ajaxFun( data );
            }
            else {
                console.log("Dialog cancelled")
            }
        });

        /**
         * ----------------------Delete a Root Event------------------------------------
         */
        //On delete root event
        $('a.tennis-delete-event.root').on('click', (event) => {
            console.log("Delete root event");
            const eventId = event.target.dataset.eventid
            console.log("EventId=%d",eventId)
            if(confirm("Are you sure you want to delete this event?")) {
                ajaxFun(  {"task": 'deleterootevent', "eventId": eventId } );
            }
        });

        /**
         * ---------------------Add a Bracket------------------------------------
         */
        //On Add a new bracket
        $('.tennis-add-bracket').on('click', (event) => {
            console.log("add bracket");
            console.log(event.target);
            $(this).prop('disabled', true );
            let eventId = event.target.getAttribute("data-eventid");
            let newName = uniqueBracketName(eventId);
            ajaxFun( {"task": "addbracket"
                    , "eventId": eventId
                    , "bracketName": newName } );
        });

        /**
         * ---------------------Remove a Bracket------------------------------------
         */
        //On Remove a bracket
        $('.tennis-event-brackets').on('click', '.remove-bracket', function (event) {
            console.log("remove bracket");
            console.log(this);
            let bracketdata = getBracketData(this);
            $(this).removeData('beforeContentEdit')
            //let eventId = tennis_bracket_obj.eventId; 
            if(confirm("Are you sure you want to delete this bracket?")) {
                //$(this).parent().hide()
                hideBracket(  bracketdata.eventid, bracketdata.bracketnum )
                let config =  {"task": "removebracket"
                            , "eventId": bracketdata.eventid
                            , "bracketNum": bracketdata.bracketnum
                            , "bracketName": bracketdata.bracketName }
                console.log(config)
                ajaxFun( config );
            }
        });
        
        /**
         * ---------------------Copy Ladder Events------------------------------------
         */
        //On click to Copy an event for next month's ladder
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

        //Enable the bracket delete x
        enableDeleteBracketButton();

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
         * ------------------------The following creates JQuery tabs based on parent events------------------------------
         * Necessary because the number of tabs and panels is dynamic becaused it is based on the root events rendered
         */
        $("#tabs").prepend(`<ul class="tennis-event-tabs"></ul>`);//Creates ui-tabs-nav
        let $parentEvents = $('.tennis-parent-event');
        let numTabs = 0
        $parentEvents.each( function(idx, el ) {
            ++numTabs
            let title = $(this).find('ul.tennis-event-meta > li:first-child > span').text();
            let id = $(this).attr("id");
            //Create ui-tabs-tab and ui-tabs-anchor
            $( "#tabs > ul" ).append( `<li class="tennis-tab-name"><a href="#${id}">${title}</a></li>` );
        } );

        //Set tab options for ui-tab at create time
        $( ".tennis-event-tabs-container" ).tabs( {create: function( event, ui ) {
            console.log("create:")
            console.log(ui.tab)
            ui.tab.css({'border-bottom-width':'0', 'background-color':'white', 'color': 'black'})
            ui.tab.children('a').css({'color': 'black'})
            console.log(ui.panel)
            ui.panel.css({'background-color': 'beige'})
        }})

        //Set tab options for ui-tab at activate time
        $( ".tennis-event-tabs-container" ).tabs( {active: false, activate: function( event, ui ) {
            ui.newTab.css({'border-bottom-width':'0', 'background-color':'white', 'color': 'black'});
            ui.newTab.children('a').css({'color': 'black'})
            ui.oldTab.css({'border-bottom-width':'1px', 'background-color':'gray', 'color': 'white'});
            ui.oldTab.children('a').css({'color': 'white'})
            ui.oldPanel.css({'background-color': 'white'})
            ui.newPanel.css({'background-color': 'beige'})
        }})

        // Setters
        //$(".tennis-event-tabs-container" ).tabs( "option", "disabled", true );
        $( ".tennis-event-tabs-container" ).tabs( "option", "collapsible", false );
        $( ".tennis-event-tabs-container" ).tabs( "option", "active", 0 );
        $( ".tennis-event-tabs-container" ).tabs( "option", "event", "click" );
        $( ".tennis-event-tabs-container" ).tabs( "option", "hide", { effect: "fold", duration: 2000 } );
        $( ".tennis-event-tabs-container" ).tabs( "option", "show", { effect: "blind", duration: 2000 } );

        const active = $( ".tennis-event-tabs-container" ).tabs("option","active")
        console.log(`Number of tabs is ${numTabs}. The active tab index is ${active}:`)
        console.log($( ".tennis-event-tabs-container > ul" ).children().get(active))

        //Classes
        $('.tennis-event-tabs-container').tabs({"ui-tabs-nav": "tennis-event-tabs", "ui-tabs-tab": "tennis-tab-name ui-corner-all"});                                                 
    });
})(jQuery);
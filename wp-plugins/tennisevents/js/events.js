(function($) {   
 
    $(document).ready(function() {       
        let sig = '#tennis-event-message';
        console.log("Manage Events");
        console.log(tennis_event_obj);

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
            let reqData =  { 'action': tennis_event_obj.action      
                           , 'security': tennis_event_obj.security 
                           , 'data': eventData };    
            console.log("Parameters:");
            console.log( reqData );

            // Send Ajax request with data 
            let jqxhr = $.ajax( { url: tennis_event_obj.ajaxurl    
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
                    reloadWindow( data )
                    break;
                case 'addrootevent':
                    reloadWindow( data )
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
                    updateLeafEventTitle( data )
                    break;
                case 'modifyrooteventtitle':
                    updateRootEventTitle( data )
                    break;
                case 'modifyrooteventtype':
                    break;
                case 'modifyrootstartdate':
                    updateRootStartDate( data )
                    updateRootEndDate( data )
                    break;
                case 'modifyrootendate':
                    updateRootEndDate( data )
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
                    updateStartDate( data )
                    updateEndDate( data )
                    break;
                case 'modifystartdate':
                    updateStartDate( data )
                    updateSignupBy( data )
                    updateEndDate( data )
                    break;
                case 'modifyenddate':
                    updateSignupBy( data )
                    updateStartDate( data )
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
         * @param  {int} eventId
         * @param {int} bracketNum
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
         * @param {int} eventId 
         * @param {int} bracketNum 
         * @return {jQuery} object
         */
        function findBracket( eventId, bracketNum ) {
            console.log("findBracket(%d,%d)", eventId, bracketNum );
            let sel = `.item-bracket[data-eventid="${eventId}"][data-bracketnum="${bracketNum}"]`
            let $bracketElem = $(sel);
            return $bracketElem;
        }
        
        /**
         * Get all bracket data from the element/obj
         * @param {element} el Assumes that el is a child of .item-bracket
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
         * @param {element} el 
         * @returns {Object} with properties of a leaf event
         */
        function getLeafEventData( el ) {
            let $parent = $(el).closest('.tennis-event-meta');
            if( $parent.length == 0) return {};
            
            let evtId = $parent.attr('id')
            let postId = $parent.attr('data-postid');
            let $genderEl = $parent.find('.gender_selector option:selected')
            let gender = ''
            if($genderEl.length === 0) {
                $genderEl = $parent.find('tbody tr td[data-gender]')
                gender = $genderEl.attr('data-gender');
            }
            else {
                gender = $genderEl.val();
            }
            let minAge = $parent.find('.min_age_input').val();
            let maxAge = $parent.find('.max_age_input').val();
            let signupBy = $parent.find('.signup_by_input').val();
            let startDate = $parent.find('.start_date_input').val();
            let endDate = $parent.find('.end_date_input').val();
            //let format = $parent.find('.format_selector option:selected').val();
            let $formatEl = $parent.find('.format_selector option:selected');
            let format = ''
            if($formatEl.length === 0) {
                format = $parent.find('tbody tr td[data-format]').attr('data-format')
            }
            else {
                format = $formatEl.val();
            }
            let scoreRule = $parent.find('.score_rules_selector option:selected').val();
            let $matchTypeEl = $parent.find('.match_type_selector option:selected');
            let matchType = ''
            if($matchTypeEl.length === 0) {
                matchType = $parent.find('tbody tr td[data-matchtype]').attr('data-matchtype')
            }
            else {
                matchType = $matchTypeEl.val();
            }
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
        /**
         * -------------------------Root Events----------------------------------------------------------
         */
        function updateRootEventTitle( data ) {
            console.log('updateRootEventTitle')
            console.log(data)

            //Handle the tab first
            const selTab = `li > a[href='#${data.postId}']`
            let $tabEl =$(selTab)
            $tabEl.text(data.newTitle)

            //Now handle the event title
            const selParent = `div.tennis-parent-event[data-event-id='${data.eventId}']`;
            const $parentEl=$(selParent)
            $titleEl = $parentEl.find('span.tennis-parent-event-title')
            $titleEl.text(data.newTitle)
            $titleEl.removeData('oldTitle')
        }

        function updateRootStartDate( data ) {
            console.log('updateRootStartDate')
            console.log(data)
            const selParent = `div.tennis-parent-event[data-event-id='${data.eventId}']`
            const $parentEl = $(selParent)
            let $startEl = $parentEl.find('li.tennis-root-event-date.start input[type="date"]')
            $startEl.val(data.startDate)
        }

        function updateRootEndDate( data ) {
            console.log('updateRootEndDate')
            console.log(data)
            const selParent = `div.tennis-parent-event[data-event-id='${data.eventId}']`
            const $parentEl = $(selParent)
            let $endEl = $parentEl.find('li.tennis-root-event-date.end input[type="date"]')
            $endEl.val(data.endDate)
        }

        /**--------------------------------Leaf Events aka Tournaments----------------------------------- */
        
        //Update the min age
        function updateMinAge( data ) {
            console.log('updateMinAge')
            console.log(data)
            setActiveTab(data)
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

        function updateLeafEventTitle( data ) {
            console.log('updateLeafEventTitle')
            console.log(data)
            let $titleEl = $(`.tennis-leaf-event-title[data-eventid='${data['eventId']}'`)
            $titleEl.text(data['newTitle'])
            $titleEl.removeData('oldTitle')
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
            const selParent = `table.tennis-event-meta[data-eventid='${data.eventId}']`
            const $parentEl = $(selParent)
            let $formatEl = $parentEl.find('select.format_selector')
            $formatEl.val(data.eventFormat)
        }

        //Update the match type
        function updateMatchType( data ) {
            console.log('updateMatchType')
            console.log(data)            
            const selParent = `table.tennis-event-meta[data-eventid='${data.eventId}']`
            const $parentEl = $(selParent)
            console.log($parentEl)

        }

        //Update the signup by date 
        function updateSignupBy( data ) {
            console.log('updateSignupBy')
            console.log(data)
            const selParent = `table.tennis-event-meta[data-eventid='${data.eventId}']`
            const $parentEl = $(selParent)
            let $signupEl = $parentEl.find('input.signup_by_input[type="date"]')
            $signupEl.val(data.signupBy)
        }

        //Update the start date
        function updateStartDate( data ) {
            console.log('updateStartDate')
            console.log(data) 
            const selParent = `table.tennis-event-meta[data-eventid='${data.eventId}']`
            const $parentEl = $(selParent)
            let $startEl = $parentEl.find('input.start_date_input[type="date"]')
            $startEl.val(data.startDate)
        }

        //Update the end date
        function updateEndDate( data ) {
            console.log('updateEndDate')
            console.log(data)
            const selParent = `table.tennis-event-meta[data-eventid='${data.eventId}']`
            const $parentEl = $(selParent)
            let $endEl = $parentEl.find('input.end_date_input[type="date"]')
            $endEl.val(data.endDate)         
        }

        function updateScoreType( data ) {
            console.log('updateScoreType')
            console.log(data)
        }

        /*---------------------------------Brackets-----------------------------------------------*/
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
            let config =  {"task": "editname"
                            , "eventId": bracketdata.eventid
                            , "bracketNum": bracketdata.bracketnum
                            , "bracketName": bracketName
                            , "oldBracketName": oldBracketName
                             }
            console.log(config)
            ajaxFun( config );
        }
    
        /**------------------------------------Functions supporting DOM Events--------------------------------------------- */
        /**
         * Change the title of the root event
         */
        const onChangeRootTitle = function( event ) {
            console.log("onChangeRootTitle fired!")
            console.log("Target:")
            console.log(event.target)
            console.log("this:")
            console.log(this)

            const eventId = $(this).closest(".tennis-parent-event").attr("data-event-id");
            const postId = $(this).closest(".tennis-parent-event").attr("id");
            let eventTitle = (event.target.innerText || '').trim();
            //if( eventTitle === '') return;

            let oldTitle = $(event.target).data('beforeContentEdit').trim();
            $(event.target).removeData('beforeContentEdit')
            let config =  {"task": "modifyrooteventtitle"
                            , "eventId": eventId
                            , "postId": postId
                            , "newTitle": eventTitle
                            , "oldTitle": oldTitle }
            console.log(config)
            ajaxFun( config );
        }
        
        /**
         * Change the title of the tournament (i.e. leaf event)
         */
        const onChangeTournamentTitle = function( event ) {
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
                            , "oldTitle": oldTitle}
            console.log(config)
            ajaxFun( config );
        }

        //Change Root Event Type
        const onChangeEventType = function(event, postIt = true) {
            console.log("onChangeEventType")
            console.log(event.target)
            const newVal = event.target.value;
            console.log(`Event type change detected: ${newVal} with post=${postIt}`)
            const eventId = $(event.target).closest(".tennis-parent-event").attr("data-event-id");
            const postId = $(event.target).closest(".tennis-parent-event").attr("id");
            let args = {"task": "modifyrooteventtype", "eventId": eventId, "postId": postId, "eventType": newVal }
            if(postIt === true) {
                ajaxFun( args );
            }

        }

        //Change Root Start Date
        const onChangeRootStartDate = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Root Start Date change detected: ${newVal} with post=${postIt}`)
            console.log("Target:")
            console.log(event.target)
            const eventId = $(event.target).closest(".tennis-parent-event").attr("data-event-id");
            const postId = $(event.target).closest(".tennis-parent-event").attr("id");
            const args = {"task": "modifyrootstartdate", "eventId": eventId, "postId": postId, "startDate": newVal }
            console.log(args)
            if(postIt === true) {
                ajaxFun( args );
            }
        }
        //Change Root End Date
        const onChangeRootEndDate = function( event, postIt = true ) {
            const newVal = event.target.value;
            console.log(`Root End Date change detected: ${newVal} with post=${postIt}`)
            console.log("Target:")
            console.log(event.target)
            const eventId = $(event.target).closest(".tennis-parent-event").attr("data-event-id");
            const postId = $(event.target).closest(".tennis-parent-event").attr("id");
            const args = {"task": "modifyrootenddate", "eventId": eventId, "postId": postId, "endDate": newVal }
            console.log(args)
            if(postIt === true) {
                ajaxFun( args );
            }
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
        
        /*---------------------------------- DOM Event Listeners -------------------------------------*/

        /**
         * ----------------------Root Events listeners-------------------------------------
         */
         //Root Event title change
         $('.tennis-root-event-title > span[contenteditable]').on('change', onChangeRootTitle);
         $('.tennis-root-event-title > span[contenteditable]').on('focus', onFocus);
         $('.tennis-root-event-title > span[contenteditable]').on('blur', onBlur);
         
        //Root Event event tyoe
        $(".tennis-root-event-type.event-type-selector").on('change',onChangeEventType);

        //Root Event Start date
        $(".tennis-root-event-date.start > input[type='date']").on("change", onChangeRootStartDate);

        //Root Event End date
        $(".tennis-root-event-date.end > input[type='date']").on("change",onChangeRootEndDate);

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
                const eventType = $form.find("select[name='eventtype'").val()
                const startDate = $form.find("input[name='startdate']").val()
                const endDate = $form.find("input[name='enddate']").val()
                let data = {"title": title
                            ,"eventType": eventType
                            ,"startDate": startDate
                            ,"endDate": endDate
                        }
                data.task = "addrootevent"
                console.log(data)
                setActiveTab(0)
                ajaxFun( data );
            }
            else {
                console.log("Dialog cancelled")
            }
        });

        //On delete root event
        $('a.tennis-delete-event.root').on('click', (event) => {
            console.log("Delete root event");
            const eventId = event.target.dataset.eventid
            console.log("EventId=%d",eventId)
            if(confirm("Are you sure you want to delete this event?")) {
                setActiveTab(0)
                ajaxFun(  {"task": 'deleterootevent', "eventId": eventId} );
            }
        });

        /**
         * ----------------------Leaf Events aka Tournaments Listeners-------------------------------------
         */
         //Leaf Event title change
         $('.tennis-leaf-event-title[contenteditable]').on('change', onChangeTournamentTitle);
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
        //Leaf
        $(".start_date_input").on("change", function(event) {
             onChangeStartDate(event);
        });
 
        //On Change the End Date    
        //Leaf
        $(".end_date_input").on("change", function(event) {
              onChangeEndDate(event);
        });
        
        //On Change the score type 
        $(".score_rules_selector").on("change", function(event) {
            onChangeScoreRule(event);
        });

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
         * ----------------------Brackets Listeners-------------------------------------
         */
         //On Bracket name change
         $('span.bracket-name').on('change', onChangeBracketName);
         $('span.bracket-name').on('focus', onFocus);
         $('span.bracket-name').on('blur', onBlur);

        //On Add a new bracket
        $('.tennis-add-bracket').on('click', (event) => {
            console.log("add bracket");
            console.log(event.target);
            $(this).prop('disabled', true );
            let eventId = event.target.getAttribute("data-eventid");
            let newName = uniqueBracketName(eventId);
            ajaxFun( {"task": "addbracket"
                    , "eventId": eventId
                    , "bracketName": newName} );
        });

        //On Remove a bracket
        $('.tennis-event-brackets').on('click', '.remove-bracket', function (event) {
            console.log("remove bracket");
            console.log(this);
            let bracketdata = getBracketData(this);
            $(this).removeData('beforeContentEdit')
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
         * ---------------------Copy Ladder Events Listeners------------------------------------
         */
        //On click to prepare next month's ladder
         $('.tennis-parent-event').on('click', '.tennis-ladder-next-month', function (event) {
            console.log("Prepare next month");
            console.log(event.target);
            const $parentEl = $(event.target).closest('.tennis-parent-event')
            let eventId = $parentEl.attr("data-event-id");
            let postId = $parentEl.attr("id");
            let data = {"eventId": eventId, "postId": postId }
            data.task = "preparenextmonth"
            if(confirm("Are you sure you want to prepare next month?")) {
                console.log(data)
                ajaxFun( data );
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

        /*--------------------------Local Storage---------------------------------*/
        function storageAvailable(type) {
            var storage;
            try {
              storage = window[type];
              var x = "__storage_test__";
              storage.setItem(x, x);
              storage.removeItem(x);
              return true;
            } catch (e) {
              return (
                e instanceof DOMException &&
                // everything except Firefox
                (
                  // test name field too, because code might not be present
                  // everything except Firefox
                  e.name === "QuotaExceededError" ||
                  // Firefox
                  e.name === "NS_ERROR_DOM_QUOTA_REACHED") &&
                // acknowledge QuotaExceededError only if there's something already stored
                storage &&
                storage.length !== 0
              );
            }
          }

        /**
         * Gets the active tab index from local storage
         * @returns {int}
         */
        function getActiveTab() {
            if(localStorage.getItem('activeTab')) {
                return localStorage.getItem('activeTab')
            }
            return 0;
        }

        /**
         * Stores the active tab index into local storage
         * @param {int} active 
         */
        function setActiveTab( active ) {
            active = active || 0
            localStorage.setItem('activeTab', active)
        }
        
        //Test for storage
        if (storageAvailable("localStorage")) {
            console.log("Yippee! We can use localStorage awesomeness");
        } else {
            console.log("Too bad, no localStorage for us");
        }
    
        /**
         * ------------------------The following creates JQuery tabs based on parent events------------------------------
         * Necessary because the number of tabs and panels is dynamic with each request
         */
        $("#tabs").prepend(`<ul class="tennis-event-tabs"></ul>`);//Creates ui-tabs-nav
        let numTabs = 0
        $('.tennis-parent-event').each( function( idx, el ) {
            ++numTabs
            let title = $(this).find('ul.tennis-event-meta > li:first-child > span').text();
            let id = $(this).attr("id");
            //Create ui-tabs-tab and ui-tabs-anchor
            $( "#tabs > ul" ).append( `<li class="tennis-tab-name"><a href="#${id}">${title}</a></li>` );
        } );

        //Set tab options for ui-tab at create time
        const tabColor = "LightYellow"
        $( ".tennis-event-tabs-container" ).tabs( {create: function( event, ui ) {
            console.log("create:")
            console.log(ui.tab)
            ui.tab.css({'border-bottom-width':'0', 'background-color':'white', 'color': 'black'})
            ui.tab.children('a').css({'color': 'black'})
            console.log(ui.panel)
            ui.panel.css({'background-color': tabColor})
        }})

        //Set tab options for ui-tab at activate time
        $( ".tennis-event-tabs-container" ).tabs( {active: false, activate: function( event, ui ) {
            ui.newTab.css({'border-bottom-width':'0', 'background-color':'white', 'color': 'black'});
            ui.newTab.children('a').css({'color': 'black'})
            ui.oldTab.css({'border-bottom-width':'1px', 'background-color':'gray', 'color': 'white'});
            ui.oldTab.children('a').css({'color': 'white'})
            ui.oldPanel.css({'background-color': 'white'})
            ui.newPanel.css({'background-color': tabColor})
        }})

        // Setters
        //$(".tennis-event-tabs-container" ).tabs( "option", "disabled", true );
        $( ".tennis-event-tabs-container" ).tabs( "option", "collapsible", false );
 
        let activeTab=getActiveTab()
        $( ".tennis-event-tabs-container" ).tabs( "option", "active", activeTab );
        $( ".tennis-event-tabs-container" ).tabs( "option", "event", "click" );
        $( ".tennis-event-tabs-container" ).tabs( "option", "hide", { effect: "fold", duration: 2000 } );
        $( ".tennis-event-tabs-container" ).tabs( "option", "show", { effect: "blind", duration: 2000 } );

        $(".tennis-event-tabs-container" ).on('tabsactivate', (event)=>{
            const active = $( ".tennis-event-tabs-container" ).tabs("option","active")
            console.log(`Tab activated; ${active}`) 
            setActiveTab(active)
        })
        const active = $( ".tennis-event-tabs-container" ).tabs("option","active")
        console.log(`Number of tabs is ${numTabs}. The initial active tab index is ${active}:`)
        //console.log($( ".tennis-event-tabs-container > ul" ).children().get(active))
        setActiveTab(active)
        //Classes
        $('.tennis-event-tabs-container').tabs({"ui-tabs-nav": "tennis-event-tabs", "ui-tabs-tab": "tennis-tab-name ui-corner-all"});                                                 
    });
})(jQuery);
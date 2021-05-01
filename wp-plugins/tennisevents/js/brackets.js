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
        
        function storageAvailable(type) {
            var storage;
            try {
                storage = window[type];
                var x = '__storage_test__';
                storage.setItem(x, x);
                storage.removeItem(x);
                return true;
            }
            catch(e) {
                return e instanceof DOMException && (
                    // everything except Firefox
                    e.code === 22 ||
                    // Firefox
                    e.code === 1014 ||
                    // test name field too, because code might not be present
                    // everything except Firefox
                    e.name === 'QuotaExceededError' ||
                    // Firefox
                    e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                    // acknowledge QuotaExceededError only if there's something already stored
                    (storage && storage.length !== 0);
            }
        }

        /**
         * Apply the response from an ajax call to the elements of the page
         * For example, recording scores or defaulting a player or adding comments
         * @param {} data 
         */
        function applyResults( data ) {
            data = data || [];
            console.log("Apply results:");
            console.log( data );
            if( $.isArray(data) ) {
                console.log("Data is an array");
                let task = data['task'];
                switch(task) {
                    case 'editname':
                        updateBracketName( data );
                        break;
                    case 'addbracket':
                        addBracket( data );
                        break;
                    default:
                        console.log("Unknown task from server: '%s'", task);
                        break;
                }
            }
            else if( isString( data )) {
                console.log("Data is a string ... so doing nothing!");
            }
            else {
                console.log("Data is an object");
                let task = data.task;
                switch(task) {
                    case 'editname':
                        updateBracketName( data );
                        break;
                    case 'addbracket':
                        addBracket( data );
                        break;
                    case 'removebracket':
                        hideBracket( data );
                        break;
                    default:
                        console.log("Unknown task from server: '%s'", task);
                        break;
                }
            }
        }

        /**
         * Update the bracket name
         * @param {*} data 
         */
        function updateBracketName( data ) {
            console.log('updateBracketBracket');
            let $matchEl = findBracket( data.eventId, data.bracketNum );
            $matchEl.children('.bracket-name').text(data.bracketName);
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
            $('#add-bracket').prop('disabled', false );
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
        
        /* ------------------------------User Actions ---------------------------------*/

         $('span.bracket-name').on('change', onChange);
         $('span.bracket-name').on('focus', onFocus);
         $('span.bracket-name').on('blur', onBlur);

        /**
         * Add a new bracket
         */
        $('#add-bracket').on('click', function (event) {
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

        enableDeleteButton();

        /**
         * Remove a bracket
         */
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
        
        //Test
        // if (storageAvailable('localStorage')) {
        //     console.log("Yippee! We can use localStorage awesomeness");
        //   }
        //   else {
        //     console.log("Too bad, no localStorage for us");
        //   }
    });
})(jQuery);
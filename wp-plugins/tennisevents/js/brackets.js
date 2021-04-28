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
            $parent = $('.tennis-event-brackets');
            window.location.reload(); //TODO: don't reload, add the necessary elements
            //let $matchEl = findBracket( data.eventId, data.bracketNum );
        }

        /**
         * Find the match element on the page using its composite identifier.
         * It is very important that this find method is based on event/bracket identifiers and not on css class or element id.
         * @param int eventId 
         * @param int bracketNum 
         */
        function findBracket( eventId, bracketNum ) {
            console.log("findMatch(%d,%d)", eventId, bracketNum );
            let attFilter = '.item-bracket[data-eventid="' + eventId + '"]';
            attFilter += '[data-bracketnum="' + bracketNum + '"]';
            let $matchElem = $(attFilter);
            return $matchElem;
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
                        , "newBracketName": bracketName };
            // console.log("getBracketData....");
            // console.log(data);
            return data;
        }
        
        /* ------------------------------User Actions ---------------------------------*/

        /**
         * Change the home player/entrant
         *  Can only be done if bracket is not yet approved
         */
        $('span.bracket-name').on('change', function (event) {
            console.log("change bracket name");
            console.log(this);
            let bracketdata = getBracketData(this);
            let bracketName = this.innerText;
            let oldBracketName = $(this).data('beforeContentEdit');
            $(this).removeData('beforeContentEdit')
            //let eventId = tennis_bracket_obj.eventId; 
            let config =  {"task": "editname"
                            , "eventId": bracketdata.eventid
                            , "bracketNum": bracketdata.bracketnum
                            , "bracketName": bracketName
                            , "oldBracketName": oldBracketName }
            console.log(config)
            ajaxFun( config );
        });

        // console.log("All Brack Spans")
        // console.log($('span.bracket-name'));
        $('span.bracket-name').on('focus', function(event) {
            console.log("on focus");
            console.log(this);
            console.log(this.innerText);
            $(this).data('beforeContentEdit', this.innerText);
            //$(this).attr('data-beforeContentEdit',this.innerText);
        });
        
        $('span.bracket-name').on('blur', function(event) {
            console.log("on blur")
            console.log(this)     
            if ($(this).data('beforeContentEdit') !== this.innerText) {
                let ans = prompt("Save the new name: ",this.innerText )
                if( ans = 'y') {
                    $(this).trigger('change');
                }
                else {
                    this.innerText = $(this).data('beforeContentEdit');
                    $(this).removeData('beforeContentEdit')
                }
            }
        });

        // Options for the observer (which mutations to observe)
        //const config = { attributes: false, characterDataOldValue: true, characterData: true, subtree: true, childList: true};

        // Callback function to execute when mutations are observed
        // const callback = function(mutationsList, observer) {
        //     // Use traditional 'for loops' for IE 11
        //     for(const mutation of mutationsList) {
        //         console.log(mutation.target.parentNode);
        //         if (mutation.type === 'childList') {
        //             console.log('A child node has been added or removed.');
        //         }
        //         else if (mutation.type === 'attributes') {
        //             console.log('The ' + mutation.attributeName + ' attribute was modified.');
        //         }
        //         else if (mutation.type === 'characterData') {
        //             console.log('The text was modified from: ' + myTrim(mutation.oldValue) + ' to ' + myTrim(mutation.target.textContent));
        //         }
        //     }
        // };       
        // create an observer instance
        // let observer = new MutationObserver( callback );
        // const targetNode = document.getElementById('tennis-event-brackets');
        // observer.observe(targetNode, config)

        /**
         * Default the home entrant/player
         */
        $('#add-bracket').on('click', function (event) {
            console.log("add bracket");
            console.log(event.target);
            let eventId = event.target.getAttribute("data-eventid");
            let bracketName = prompt("Please enter name of bracket.",eventId);
            if( null == bracketName ) {
                return;
            }

            ajaxFun( {"task": "addbracket"
                    , "eventId": eventId
                    , "bracketName": bracketName } );
        });
        
        //Test
        if (storageAvailable('localStorage')) {
            console.log("Yippee! We can use localStorage awesomeness");
          }
          else {
            console.log("Too bad, no localStorage for us");
          }
    });
})(jQuery);
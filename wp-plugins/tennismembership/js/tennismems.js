"use strict";
(function($) {   
    $(document).ready(function() {       
        let sig = '#tennis-member-message';
        console.log("Manage People");
        console.log(tennis_member_obj);

        var longtimeout = 60000;
        var shorttimeout = 5000;

        const isString = str => ((typeof str === 'string') || (str instanceof String));
        const myTrim = str => str.replace(/^\s+|\s+$/gm,'');

        /**
         * Function to make ajax calls.
         * Needs the "action" and "security" nonce from the local object emitted from the server
         * @param {} memberData 
         */
        let ajaxFun = function( memberData ) {
            console.log('Membership Management: ajaxFun');
            let reqData =  { 'action': tennis_member_obj.action      
                            , 'security': tennis_member_obj.security 
                            , 'data': memberData };    
            console.log("Parameters:");
            console.log( reqData );
            // Send Ajax request with data 
            let jqxhr = $.ajax( { url: tennis_member_obj.ajaxurl    
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
                case 'convertusers':
                    reloadWindow( data )
                    break;
                case 'adduser':
                    reloadWindow( data )
                    break;
                case 'deleteuser':
                    reloadWindow( data )
                    break;
                case 'updatePerson':
                    updatePersonData( data )
                    break;
                case 'addPerson':
                    //reloadWindow( data )
                    addSponsoredData( data )
                    break;
                case 'deletePerson':
                    //reloadWindow( data )
                    deleteSponsoredData( data )
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

        /**--------------------------------update in place handlers----------------------------------- */
        function deleteSponsoredData( data ) {
            console.log('deleteSponsoredData');
            console.log(data);
            let $el = $(`section.membership.sponsored[data-sponsoredid='${data.personId}']`);
            let $hr = $(`section.membership.sponsored[data-sponsoredid='${data.personId}'] + hr`);
            if( $el.length === 0 ) {
                console.log("No sponsored element found, so not deleting sponsored person");
                console.log(`section.membership.sponsored[data-sponsoredid='${data.personId}']`);
                return;
            }
            $el.remove();
            $hr.remove();
        }

        function updatePersonData( data ) {
            console.log('updatePersondata')
            console.log(data)
            let $showButton = null;
            let $parent = $(`article.membership[data-person-id='${data.personId}']`); 
            if( data.sponsored && data.sponsored === true ) {
                console.log("Updating sponsored person data");
                $parent = $(`article > section.membership[data-sponsoredid='${data.personId}']`); 
                $showButton = $parent.find('button.button.membership.show-sponsored');
            } else {
                console.log("Updating regular person data");
            }
            //inputs 
            $parent.find('input.membership.first-name').val(data.firstName);
            $parent.find('input.membership.last-name').val(data.lastName);
            $parent.find('input.membership.home-email').val(data.homeEmail);
            $parent.find('input.membership.home-email').attr('data-orig-value',data.homeEmail);
            $parent.find('input.membership.home-phone').val(data.homePhone);
            $parent.find('input.membership.birth-date').val(data.birthDate);   
            $parent.find('select.membership.gender-selector').val(data.gender);
            //span
            $parent.find('li.membership.sponsor.first > span').text(data.firstName);
            $parent.find('li.membership.sponsor.last > span').text(data.lastName);
            $parent.find('li.membership.sponsor.home-phone > span').text(data.homePhone);
            $parent.find('li.membership.sponsor.home-email > a').text(data.homeEmail);
            $parent.find('li.membership.sponsor.home-email > a').attr('href','mailto:' + data.homeEmail);
            $parent.find('li.membership.sponsor.gender > span').text(data.gender);
            $parent.find('li.membership.sponsor.birthdate > span').text(data.birthDate);
            //display
            $parent.find("div.membership.sponsor").css('display','block');
            if( $showButton ) {
                $showButton.text('Show');
            }
            $parent.find("form.membership.sponsor").css('display','none');
        }
            
        /**
         * Add a sponsored person to the DOM
         * @param array data 
         * @returns void
         */
        function addSponsoredData( data ) {
            console.log('addSponsoredData');
            console.log(data);
            let $sponsorEl = $(`article.membership.sponsored[data-sponsorid='${data.personId}']`);
            if( $sponsorEl.length === 0 ) {
                console.log("No sponsor element found, so not adding sponsored person");
                console.log(`article.membership.sponsored[data-sponsorid='${data.personId}']`);
                return;
            }
            console.log("Adding sponsored to element:");
            console.log($sponsorEl);
            //Create the new sponsored person element
            let $section = $('<section>',{class: "membership sponsored", id: data.sponsoredId, "data-sponsoredid": data.sponsoredId });
            //$section.append(template);
            let $menu = $('<ul>',{ class:"membership sponsored full-name",})
            let $menuitem1 = $('<li>',{ class:"membership sponsored full-name", html: data.firstName + ' ' + data.lastName });
            $menu.append($menuitem1);

            let $menuitem2 = $('<li>',{ class:"membership sponsored" });
            let $showButton = $('<button>',{ type:'submit', class:'button membership show-sponsored', html:"Show"});
            $menuitem2.append($showButton);
            $menu.append($menuitem2);

            let $menuitem3 = $('<li>',{ class:"membership sponsored" });
            let $editButton = $('<button>',{ type:'submit', class:'button membership edit-sponsored', html:"Edit"});
            $menuitem3.append($editButton);
            $menu.append($menuitem3);

            let $menuitem4 = $('<li>',{ class:"membership sponsored" });
            let $deleteButton = $('<button>',{ type:'submit', class:'button membership delete-sponsored', html:"Delete"});
            $menuitem4.append($deleteButton);
            $menu.append($menuitem4);
            $section.append($menu);

            //Details
            let $details = $('<ul>',{ class:"membership sponsored details", "data-sponsoredid": data.sponsoredId });
            let $item1 = $('<li>',{ class: 'membership sponsored home-email',html: `Email:&nbsp;<a href='mailto:${data.homeEmail}'>${data.homeEmail}</a>` });
            $details.append($item1);
            let $item2 = $('<li>',{ class: 'membership sponsored gender', html: `Gender:&nbsp;<span>${data.gender}</span>` });
            $details.append($item2);
            let $item3 = $('<li>',{ class: 'membership sponsored birth-date', html: `Birthdate:&nbsp;<span>${data.birthDate}</span> (${data.age} years old)` });
            $details.append($item3);
            let $item4 = $('<li>',{ class: 'membership sponsored home-phone', html: `Home Phone:&nbsp;<span>${data.homePhone}</span>` });
            $details.append($item4);
            $section.append($details);

            // //Form
            let $form = $(`<form action="" method="post" class="membership sponsored" data-sponsoredid="${data.sponsoredId}">`);

            let $labelFirstName = $('<label>',{text: "First Name: "});
            let $firstNameInput = $('<input>', {name:'firstname', type:'text', class:'membership first-name', value: data.firstName});
            $labelFirstName.append($firstNameInput);
            $form.append($labelFirstName);

            let $labelLastName = $('<label>',{text: "Last Name: "});
            let $lastNameInput = $('<input>', {name:'lastname', type:'text', class:'membership last-name', value: data.lastName});
            $labelLastName.append($lastNameInput);
            $form.append($labelLastName);

            let $labelEmail = $('<label>',{text: "Email: "});
            let $emailInput = $('<input>', {name:'homeemail', type:'email', class:'membership home-email', value: data.homeEmail, 'data-orig-value': data.homeEmail});
            $labelEmail.append($emailInput);
            $form.append($labelEmail);

            let $labelGender = $('<label>',{html:'Gender: ' + data.genderdd});
            $form.append($labelGender);

            let $labelBirthDate = $('<label>', {text: "Birthdate: "});
            let $birthDateInput = $('<input>', {name:'birthdate', type:'date', class:'membership birth-date', value: data.birthDate});
            $labelBirthDate.append($birthDateInput);
            $form.append($labelBirthDate);

            let $labelHomePhone = $('<label>', {text: "Home Phone: "});
            let $homePhoneInput = $('<input>', {name:'homephone', type:'tel', class:'membership home-phone', value: data.homePhone});
            $labelHomePhone.append($homePhoneInput);
            $form.append($labelHomePhone);

            let $cancelButton = $('<button>',{ id:'cancel-sponsored', type:'submit', class:'button membership cancel-sponsored', html:"Cancel"});
            $form.append($cancelButton);
            let $saveButton = $('<button>',{ id:'save-sponsored', type:'submit', class:'button membership save-sponsored', html:"Save"});
            $form.append($saveButton);

            $section.append($form);

            console.log("Section created:");
            console.log($section);
            console.log("Appending section to sponsor element:");
            console.log($sponsorEl);
            $sponsorEl.append($section);   

        }

        /**
         * Get the person (sponsor or sponsored) data from the DOM
         * @param {*} el 
         * @returns 
         */
        function getPersonData( parentEl ) {
            console.log('getPersonData');
            console.log(parentEl);
            let $parent = $(parentEl);
            if($parent.length === 0) {
                console.log("No parent found");
                return {};
            }
            let personId = $parent.attr('data-sponsorid') || $parent.attr('data-sponsoredid');
            console.log(`PersonId: ${personId}`);

            let firstName = $parent.find('input.membership.first-name').val();
            let lastName = $parent.find('input.membership.last-name').val();
            let homeEmail = $parent.find('input.membership.home-email').val();
            let oldHomeEmail = $parent.find('input.membership.home-email').attr('data-orig-value');
            let homePhone = $parent.find('input.membership.home-phone').val();
            let birthDate = $parent.find('input.membership.birth-date').val();
            let gender = $parent.find('select.membership.gender-selector').val();
            return { 
                "personId": personId,
                "firstName": firstName,
                "lastName": lastName,
                "gender": gender,
                "homeEmail": homeEmail,
                "oldHomeEmail": oldHomeEmail,
                "homePhone": homePhone,
                "birthDate": birthDate
            };
        }

        /**------------------------------------Functions supporting DOM Events--------------------------------------------- */

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

        /**
         * Change the last name of the person
         * @param {*} event
         */
        const onChangeLastName = function( event ) {
            const newLastName = event.target.value || '';
            console.log(`new last name: '${newLastName}'`);
        }

        /**
         * Change the first name of the person
         * @param {*} event
         */
        const onChangeFirstName = function( event ) {
            const newFirstName = event.target.value || '';
            console.log(`new first name: '${newFirstName}'`);
        }
                
        /**
         * Change the home email of the person
         * @param {*} event
         */
        const onChangeHomeEmail = function( event ) {
            const newHomeEmail = event.target.value || '';
            const oldHomeEmail = $(event.target).attr('data-orig-value');
            console.log(`new home email: '${newHomeEmail}'; old home email: '${oldHomeEmail}'`);
        }   

        //Change Gender
        const onChangeGender = function( event ) {
            const newVal = event.target.value;
            console.log(`gender change detected: ${newVal}`)
        }  
            
        /**
         * Change the home phone of the person
         * @param {*} event
         */
        const onChangeHomePhone = function( event ) {
            const newHomePhone = event.target.value || '';
            console.log(`new home phone: '${newHomePhone}'`);
        }
        
        /**
         * Change the home phone of the person
         * @param {*} event
         */
        const onChangeBirthDate = function( event ) {
            const newBirthDate = event.target.value || '';
            console.log(`new birthdate: '${newBirthDate}'`);
        }


        /*********** Users XML File *************************************************/
        $("#user_uploads_file").css("opacity","0")
        let uploadInputUsers = document.getElementById('user_uploads_file')
        if(null !== uploadInputUsers) {
            uploadInputUsers.addEventListener('change', function (event) {
            let fr = new FileReader();
            fr.onload = function () {
                let xmlContent = fr.result;
                let parser = new DOMParser();
                let xmlDoc = parser.parseFromString(xmlContent,'text/xml');
                const errorNode = xmlDoc.querySelector("parsererror");
                if (errorNode) {
                    // parsing failed
                    console.log(errorNode.nodeValue)
                } else {
                    let bulkUsers = []
                    // parsing succeeded
                    //console.log(xmlDoc)
                    let regs = xmlDoc.getElementsByTagName('registration');
                    console.log("regs.length=%d",regs.length)
                    //console.log(regs)
                    start = localStorage.getItem('lastCount')
                    if(null == start) start=0;
                    upper = Math.min(regs.length,50)
                    //localStorage.setItem('lastCount', upper)
                    console.log(`Start=${start}; max=${upper}`)
                    for (i = 0; i < upper; i++) {
                    let registrant = regs[i];
                    //console.log(player)
                    let first = 'unknown'
                    let last = 'unknown'
                    let staffmods = ''
                    let regtype = ''
                    let stat = ''
                    let email = ''
                    let portal = ''
                    let startdate = ''
                    let expirydate = ''
                    let birthdate = ''
                    let gender = ''
                    let fob = ''
                    let mlyr = ''
                    let membernum = ''
                    for (j = 0; j < registrant.children.length; j++) {
                        switch(registrant.children[j].nodeName) {
                            case 'firstname':
                                first = registrant.children[j].textContent;
                                break;
                            case 'lastname':
                                last = registrant.children[j].textContent;
                                break;
                            case 'email':
                                email = registrant.children[j].textContent;
                                break;     
                            case 'birthdate':
                                birthdate = registrant.children[j].textContent;
                                break;
                            case 'status':
                                stat = registrant.children[j].textContent;
                                break;
                            case 'regtype':
                                regtype = registrant.children[j].textContent;
                                break;
                            case 'portal':
                                portal = registrant.children[j].textContent;
                                break;             
                            case 'startdate':
                                startdate = registrant.children[j].textContent;
                                break;          
                            case 'expirydate':
                                expirydate = registrant.children[j].textContent;
                                break;        
                            case 'gender':
                                gender = registrant.children[j].textContent;
                                break;
                            case 'staffmodules':
                                staffmods = registrant.children[j].textContent;
                                break;
                            case 'fob':
                                fob = registrant.children[j].textContent;
                                break;
                            case 'memberlastyear':
                                mlyr = registrant.children[j].textContent;
                                break;
                            case 'membernumber':
                                membernum =  registrant.children[j].textContent;
                                break;
                            }
                    }
                    console.log("%d. %s %s - %s",i, first, last, regtype)
                    let newReg = {'firstname': first,'lastname': last, 'email': email,'birthdate': birthdate, 'regtype': regtype,'membernumber': membernum,'memberlastyear': mlyr, 'gender': gender, 'fob': fob, 'portal': portal, 'startdate': startdate, 'expirydate': expirydate, 'status': stat}
                    bulkUsers.push(newReg)
                    } //regs
                    
                    console.log("bulkUsers.length=%d",bulkUsers.length)
                    regData = {"task": 'convertusers', 'name':'bulk', 'users': bulkUsers}
                    console.log(regData)
                    ajaxFun(regData);
            }
            }
            fr.readAsText(this.files[0]);
        })
        };
        

        /**************************************************************************/
    
        /**
         * ----------------------Registration Listeners-------------------------------------
         */

        /*** Buttons to edit, cancel or save the sponsor **/
        //Save the edited sponsor
        $('#save-sponsor').on('click', function(event) {
            $parent = $(event.target).parents('article.membership.sponsor');
            //let personId = $parent.attr('data-sponsorid');
            event.preventDefault();
            let config = getPersonData($parent.get(0));
            config.task = "updatePerson";
            console.log(config);
            ajaxFun( config );
        });

        //Cancel the sponsor edit
        $('#cancel-sponsor').on('click', function(event) {
            let $parent = $(event.target).parents('article.membership.sponsor');
            event.preventDefault();
            //document.querySelector("form.membership.sponsor").reset();
            let formEl = $parent.find('form.membership.sponsor').get(0);
            console.log(formEl);
            if(formEl) {
                formEl.reset();
            }
            $parent.find("ul.membership.sponsor").css('display','block');
            $parent.find("form.membership.sponsor").css('display','none');
        });
            
        //Edit the sponsor
        $('#edit-sponsor').on('click', function(event) {
            console.log("Edit sponsor");
            console.log(event.target);
            event.preventDefault();
            let $parent = $(event.target).parents('article.membership.sponsor');
            $parent.find("ul.membership.sponsor").css('display','none');
            $parent.find("form.membership.sponsor").css('display','block');
        });
        
        //Add a new sponsored person
        $('#add-sponsored').on('click', (event) => {
            console.log("Add sponsored person");
            console.log(event.target);
            const sponsorId = $(event.target).attr('data-sponsorid') || $(event.target).parents('article.membership.sponsor').attr('data-sponsorid');
            console.log(`sponsorId=${sponsorId}`);
            const selector = `dialog.membership.add-sponsored[data-sponsorid='${sponsorId}']`
            console.log(selector)
            let $dialog = $(selector)
            console.log($dialog)
            $dialog.get(0).showModal()
        });

        /*** Buttons to show, edit, cancel or save the sponsored **/
        //Show sponsored details
        $('article.membership.sponsored').on('click','button.button.membership.show-sponsored', function(event) {
            console.log("Show sponsored");
            console.log(event.target);
            event.preventDefault();
            let $parent = $(this).parents('section.membership.sponsored');
            let sponsoredId = $parent.attr('data-sponsoredid');
            console.log(`Show sponsored person with ID: ${sponsoredId}`);
            let $details = $parent.find(`ul.membership.sponsored.details[data-sponsoredid='${sponsoredId}']`);
            if($details.css('display') === 'none') {
                $details.css('display','block');
                $(this).text('Hide');
            }
            else {
                $details.css('display','none');
                $(this).text('Show');
            }
            $parent.find(`form.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','none');
        });

        //Edit the sponsored
        $('article.membership.sponsored').on('click','button.button.membership.edit-sponsored', function(event) {
            console.log("Edit sponsored");
            console.log(event.target);
            event.preventDefault();
            let $parent = $(event.target).parents('section.membership.sponsored');
            let sponsoredId = $parent.attr('data-sponsoredid');
            console.log(`Edit sponsored person with ID: ${sponsoredId}`);
            $parent.find(`ul.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','none');
            $parent.find(`form.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','block');
        });
        
        //Delete the sponsored
        $('article.membership.sponsored').on('click','button.button.membership.delete-sponsored', function(event) {
            console.log("Delete sponsored");
            console.log(event.target);
            event.preventDefault();

            let sponsorId = $(event.target).parents('article.membership.sponsored').attr('data-sponsorid');
            let $parent = $(event.target).parents('section.membership.sponsored');
            let sponsoredId = $parent.attr('data-sponsoredid');
            console.log(`Delete sponsored with person ID: ${sponsoredId}`);
            $parent.find(`ul.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','none');
            $parent.find(`form.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','none');
            
            let config = getPersonData($parent.get(0));
            config.sponsorId = sponsorId;
            config.personId = sponsoredId
            config.task = "deletePerson";
            console.log(config);
            ajaxFun( config );
        });

        //Save the sponsored
        $('article.membership.sponsored').on('click','button.button.membership.save-sponsored', function(event) {
            let $parent = $(event.target).parents('section.membership.sponsored');
            let sponsoredId = $parent.attr('data-sponsoredid');  
            console.log(`Save sponsored person with ID: ${sponsoredId}`);
            event.preventDefault();
            let $form = $parent.find(`form.membership.sponsored[data-sponsoredid='${sponsoredId}']`);
            let config = getPersonData($form.get(0));
            config.task = "updatePerson";
            console.log(config);
            ajaxFun( config );
        });

        //Cancel the edit of the sponsored
        $('article.membership.sponsored').on('click','button.button.membership.cancel-sponsored', function(event) {
            let $parent = $(event.target).parents('section.membership.sponsored');
            let sponsoredId = $parent.attr('data-sponsoredid');            
            console.log(`Cancel sponsored person with ID: ${sponsoredId}`);
            event.preventDefault();
            let formEl = $parent.find(`form.membership.sponsored[data-sponsoredid='${sponsoredId}']`).get(0);
            if(formEl) {
                formEl.reset();
            }
            $parent.find(`ul.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','none');
            $parent.find(`form.membership.sponsored[data-sponsoredid='${sponsoredId}']`).css('display','none');
        });

        /**
         * Member Basic Information Listeners
         */
        //Last name
            $('input.membership.last-name').on('change', function(event) {
            onChangeLastName(event);
        });
        
        //First name
            $('input.membership.first-name').on('change', function(event) {
            onChangeFirstName(event);
        });

            //Gender
        $("select.membership.gender-selector").on("change", function(event) {
            onChangeGender(event);
        });

        //Home phone
        $("input.membership.home-phone").on("change", function(event) {
            onChangeHomePhone(event);
        });

        //Email
        $("input.membership.home-email").on("change", function(event) {
            onChangeHomeEmail(event);
        });

        //Email
        $("input.membership.birth-date").on("change", function(event) {
            onChangeBirthDate(event);
        });

        //Submit or cancel the add sponsored dialog
        $('button.membership.add-sponsored-close').on('click', (event) => {
            console.log("Submit or cancel add sponsored dialog");
            console.log(event.target)
            //let $dialog = $(event.target).closest('dialog')
            let $dialog = $('dialog.membership.add-sponsored');
            $dialog.attr('sponsoredadd', $(event.target).val())
            console.log($dialog)
            $dialog.get(0).close()
        });

        //Take action when add sponsored dialog is closed
        $('dialog.membership.add-sponsored').on('close', (event) => {
            console.log("Add sponsored dialog closed");
            console.log(event.target);
            if($(event.target).attr('sponsoredadd') === 'submitted') {
                console.log('Dialog submitted')
                let $form = $(event.target).children('form.membership.add-sponsored')
                console.log($form)
                let allData = getPersonData($form)
                allData.task = "addPerson"
                console.log(allData)
                ajaxFun( allData );
            }
            else {
                console.log("Dialog cancelled")
            }
        });

        /**
         * ----------------------Member(ship) Switch Display Blocks-------------------------------------
         */
        $('.membership.nav').on('click','.menu.item',function(event){
            console.log('menu item')
            console.log(event.target)
            console.log($(event.target).parent('.menu.item'));
            targets = ['article.membership.history','article.membership.address','article.membership.agreement','article.membership.emergency']

            if($(event.target).parent().hasClass('history')) {
                let disp = $('article.membership.history').css('display')
                //console.log("article.membership.history has display=%s",disp)
                switch(disp) {
                    case 'block':
                        disp = 'none'
                        break;
                    case 'none':
                        disp = 'block'
                        break;
                    default:
                        disp = 'none'
                }
                //console.log("new display is %s",disp)
                $('article.membership.history').css('display',disp);
                targets.forEach(element => {if(!element.endsWith('history')) $(element).css('display','none')});
            }
            else if($(event.target).parent().hasClass('address')) {
                let disp = $('article.membership.address').css('display')
                //console.log("article.membership.address has display=%s",disp)
                switch(disp) {
                    case 'block':
                        disp = 'none'
                        break;
                    case 'none':
                        disp = 'block'
                        break;
                    default:
                        disp = 'none'
                }
                $('article.membership.address').css('display',disp);
                targets.forEach(element => {if(!element.endsWith('address')) $(element).css('display','none')});
            }
            else if($(event.target).parent().hasClass('agreement')) {
                let disp = $('article.membership.agreement').css('display')
                //console.log("article.membership.agreement has display=%s",disp)
                switch(disp) {
                    case 'block':
                        disp = 'none'
                        break;
                    case 'none':
                        disp = 'block'
                        break;
                    default:
                        disp = 'none'
                }
                $('article.membership.agreement').css('display',disp);
                targets.forEach(element => {if(!element.endsWith('agreement')) $(element).css('display','none')});
            }
            else if($(event.target).parent().hasClass('emergency')) {
                let disp = $('article.membership.emergency').css('display')
                switch(disp) {
                    case 'block':
                        disp = 'none'
                        break;
                    case 'none':
                        disp = 'block'
                        break;
                    default:
                        disp = 'none'
                }
                $('article.membership.emergency').css('display',disp);
                targets.forEach(element => {if(!element.endsWith('emergency')) $(element).css('display','none')});
            }
        });

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
            
            //Test for storage
        if (storageAvailable("localStorage")) {
            console.log("Yippee! We can use localStorage awesomeness");
        } else {
            console.log("Too bad, no localStorage for us");
        }
                                                      
    });
})(jQuery);
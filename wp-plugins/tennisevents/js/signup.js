
(function($) {

    $(document).ready(function() {       
        let sig = '#tennis-reportmessage';
        console.log("Manage Signup");
        console.log(care_pass_mgmt);

        var longtimeout = 60000;
        var shorttimeout = 15000;
        var selectionText;
        var selectionId;
        var startDate;
        var endDate;

        let ajaxFun = function( ) {
            console.log('Management Reports: ajaxFun');
            let reqData =  { 'action': care_pass_mgmt.action      
                            , 'security': care_pass_mgmt.security
                            , 'user_id' : care_pass_mgmt.user_id
                            , 'id': selectionId
                            , 'report_start': startDate
                            , 'report_end': endDate };
            //console.log( reqData );
            console.log("************************Parameters:");
            console.log( reqData );

                // Send Ajax request with data 
            let jqxhr = $.ajax( { url: care_pass_mgmt.ajaxurl    
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
                            $(sig).addClass('care-error');
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

        function generateTable(rowsData, titles, caption, _class) {
            var $table = $("<table>").addClass(_class);
            var $capt   = $("<caption>").appendTo($table);
            $capt.html(caption);
            var $tbody = $("<tbody>").appendTo($table);
            type=1;
            
            if (type == 2) {//vertical table
                if (rowsData.length !== titles.length) {
                    console.error('rows and data rows count do not match');
                    return false;
                }
                titles.forEach(function (title, index) {
                    var $tr = $("<tr>");
                    $("<th>").html(title).appendTo($tr);
                    var rows = rowsData[index];
                    rows.forEach(function (html) {
                        $("<td>").html(html).appendTo($tr);
                    });
                    $tr.appendTo($tbody);
                });
                
            } else if (type == 1) {//horizontal table 
                var valid = true;
                rowsData.forEach(function (row) {
                    if (!row) {
                        valid = false;
                        return;
                    }
        
                    if (row.length !== titles.length) {
                        valid = false;
                        return;
                    }
                });
        
                if (!valid) {
                    console.error('rows and data rows count doe not match');
                    return false;
                }
        
                var $tr = $("<tr>");
                titles.forEach(function (title, index) {
                    $("<th>").html(title).appendTo($tr);
                });
                $tr.appendTo($tbody);
        
                rowsData.forEach(function (row, index) {
                    var $tr = $("<tr>");
                    row.forEach(function (html) {
                        $("<td>").html(html).appendTo($tr);
                    });
                    $tr.appendTo($tbody);
                });
            }
        
            return $table;
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

       $('#care-report-select').change(function(e) {
            console.log("#care-report-select fired!");
            selectionText = e.target.options[e.target.selectedIndex].text;
            selectionId = e.target.value; 
            console.log("id=%s; title='%s'",selectionId, selectionText);
       });

       $('#care_clear_report').on('click', function(e) {
            $('#' + care_pass_mgmt.reporttarget).html('');
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
        
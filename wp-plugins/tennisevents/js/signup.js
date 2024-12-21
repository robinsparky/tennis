(function ($) {
  $(document).ready(function () {
    let sig = "#tennis-event-message";
    console.log("Manage Signup");
    console.log(tennis_signupdata_obj);

    var longtimeout = 60000;
    var shorttimeout = 5000;
    var minNameLength = 3;

    var signupDataMask = {
      task: "",
      name: "",
      newName: "",
      position: 0,
      newPos: 0,
      seed: 0,
      clubId: 0,
      eventId: 0,
      bracketName: "",
    };
    var signupData = null;

    let ajaxFun = function (signupData) {
      console.log("Signup Management: ajaxFun");
      let reqData = {
        action: tennis_signupdata_obj.action,
        security: tennis_signupdata_obj.security,
        data: signupData,
      };
      console.log("Parameters:");
      console.log(reqData);

      // Send Ajax request with data
      let jqxhr = $.ajax({
        url: tennis_signupdata_obj.ajaxurl,
        method: "POST",
        async: true,
        data: reqData,
        dataType: "json",
        beforeSend: function (jqxhr, settings) {
          //Disable the 'Delete and Add' buttons ???
          $(sig).show();
          $(sig).html("Working...");
        },
      })
        .done(function (res, jqxhr) {
          console.log("done.res:");
          console.log(res);
          if (res.success) {
            console.log("Success (res.data):");
            console.log(res.data);
            $(sig).html(res.data.message);
            //Do stuff with data...
            applyResults(res.data.returnData);
          } else {
            console.log("Done but failed (res.data):");
            console.log(res.data);
            toggleButtons(res.data.returnData);
            var entiremess = res.data.message + " ...<br/>";
            for (var i = 0; i < res.data.exception.errors.length; i++) {
              entiremess += res.data.exception.errors[i][0] + "<br/>";
            }
            $(sig).addClass("tennis-error");
            $(sig).html(entiremess);
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          console.log("fail");
          console.log("Error: %s -->%s", textStatus, errorThrown);
          var errmess = "Error: status='" + textStatus + "--->" + errorThrown;
          errmess += jqXHR.responseText;
          console.log("jqXHR:");
          console.log(jqXHR);
          $(sig).addClass("tennis-error");
          $(sig).html(errmess);
        })
        .always(function () {
          console.log("always");
          setTimeout(function () {
            $(sig).html("");
            $(sig).removeClass("tennis-error");
            $(".eventSignup").children("li").removeClass("entrantHighlight");
            $(sig).hide();
          }, shorttimeout);
        });

      return false;
    };

    function formatDate(date) {
      var d = new Date(date),
        month = "" + (d.getMonth() + 1),
        day = "" + d.getDate(),
        year = d.getFullYear();

      if (month.length < 2) month = "0" + month;
      if (day.length < 2) day = "0" + day;

      return [year, month, day].join("-");
    }

    function applyResults(data) {
      data = data || [];
      console.log("applyResults:");
      console.log(data);
      toggleButtons(data.numPreliminary);
      if (data.task === "reseqSignup") {
        //window.location.reload();
      } else if (data.task.startsWith("createPrelim")) {
        //toggleButtons( data.numPreliminary );
        window.location = $("a.link-to-draw").attr("href");
      }
      else if (data.task.startsWith("addBulk")) {
        window.location.reload();
        return
      }

      ctr = 1;
      for (var i = 0; i < data.entrants.length; i++) {
        let entrant = data.entrants[i];
        let key = "#" + entrant.name.replace(/ /g, "_");
        //console.log(entrant);
        if ($(key).length > 0) {
          //$(key).children(".entrantPosition").html( ctr++ + ".");
          $(key)
            .children(".entrantPosition")
            .html(entrant.position + ".");
          $(key).attr("data-currentPos", entrant.position);
          $(key).children("input.entrantName").val(entrant.name);
          $(key).children("input.entrantName").attr('data-oldname',entrant.name);
          $(key).children("input.entrantSeed").val(entrant.seed);
        } else {
          console.log("Could not find entrant at index=" + i);
          console.log(entrant);
        }
      }
    }

    
    //Make ths list of entrants sortable
    // but only if not in readonly mode
    $el = $(".entrantSignupReadOnly")
    if($el.length == 0) {
      $("ul.eventSignup").sortable({
        items: "> li",
        /*handle: ".entrantPosition",*/
        forcePlaceholderSize: true,
        placeholder: "placeholderHighlight",
        cursor: "move",
        tolerance: "pointer",
        opacity: 0.7,
        scrollSpeed: 10,
        stop: handleSortStop,
      });

      $( "ul.eventSignup" ).disableSelection();
    }

    $("#tennis-event-message").draggable();

    function getMaxPosition() {
      let max = 0.0;
      $(".eventSignup")
        .children("li")
        .each(function () {
          if (parseFloat(this.dataset.currentpos) > max) {
            max = parseFloat(this.dataset.currentpos);
          }
        });
      return max;
    }

    //Move an entrant
    function handleSortStop(event, ui) {
      console.log("handleSortStop");
      console.log(event);
      console.log(ui);
      let item = ui.item;
      console.log("item:");
      console.log(item);
      let target = $(event.target);
      console.log(target);

      //NOTE: this == event.target == ul (droppable)
      $(item).addClass("entrantHighlight");

      let name = $(item).attr("id").replace(/_/g, " ");

      let maxPos = getMaxPosition();
      console.log("Maximum position is %d", maxPos);

      console.log($(item).attr("data-currentpos"));
      let currentPos = parseFloat($(item).attr("data-currentpos"));
      if (isNaN(currentPos)) {
        console.log("Current Position is not a number");
        alert("Current position is not a number.");
        window.location.reload();
        return;
      }

      let prevPos = 0;
      if ($(item).prev()) {
        prevPos = parseFloat($(item).prev().attr("data-currentpos"));
      }
      let nextPos = 0;
      if ($(item).next()) {
        nextPos = parseFloat($(item).next().attr("data-currentpos"));
      }

      let newPos = (prevPos + nextPos) / 2.0;
      if (prevPos === 0) {
        newPos = nextPos - 0.5;
      } else if (nextPos === 0) {
        newPos = maxPos + 1;
      }
      console.log(
        "prevPos=" + prevPos + "; nextPos=" + nextPos + "; moveTo=" + newPos
      );
      if (isNaN(newPos)) {
        console.log("Move To is not a number");
        alert("Move to position is not a number.");
        window.location.reload();
        return;
      }

      $(item).attr("data-currentpos", newPos);
      $(item).children(".entrantPosition").html(newPos);

      signupData = signupDataMask;
      signupData.task = "move";
      signupData.name = name;
      signupData.currentPos = currentPos;
      signupData.newPos = newPos;

      signupData.clubId = $(".signupContainer").attr("data-clubid");
      signupData.eventId = $(".signupContainer").attr("data-eventid");
      signupData.bracketName = $(".signupContainer").attr("data-bracketname");
      ajaxFun(signupData);
    }

    //Toggle the create/remove preliminary matches buttons
    function toggleButtons(numPreliminary) {
      numPreliminary = numPreliminary || 0;
      if (numPreliminary < 1) {
        $("#createPrelimRandom").prop("disabled", false);
        $("#createPrelimNoRandom").prop("disabled", false);
        $("#reseqSignup").prop("disabled", false);
        $("button.entrantDelete").prop("disabled", false);
        $("#addEntrant").prop("disabled", false);
        $("input.entrantName").prop("disabled", false);
        $("input.entrantSeed").prop("disabled", false);
      } else {
        $("#createPrelimRandom").prop("disabled", true);
        $("#createPrelimNoRandom").prop("disabled", true);
        $("#reseqSignup").prop("disabled", true);
        $("button.entrantDelete").prop("disabled", true);
        $("#addEntrant").prop("disabled", true);
        $("input.entrantName").prop("disabled", true);
        $("input.entrantSeed").prop("disabled", true);
      }
    }

    //Modify Seed
    $(".eventSignup").on("change", "input.entrantSeed", function (e) {
      console.log("Seed change fired!");
      entrantId = $(this).parent("li.entrantSignup").attr("id");
      console.log("entrantId=%s", entrantId);
      signupData = signupDataMask;
      signupData.task = "update";
      signupData.seed = $(this).val();
      if (signupData.seed < 0 || signupData.seed > 100) return;

      signupData.newName = "";
      console.log("New seed value is %d", signupData.seed);
      signupData.name = entrantId.replace(/_/g, " ").replace(/'/g, "'");
      signupData.position = $(this)
        .parent("li.entrantSignup")
        .attr("data-currentPos");

      signupData.clubId = $(".signupContainer").attr("data-clubid");
      signupData.eventId = $(".signupContainer").attr("data-eventid");
      signupData.bracketName = $(".signupContainer").attr("data-bracketname");
      ajaxFun(signupData);
    });

    //Modify Name
    $(".eventSignup").on("change", "input.entrantName", function (e) {
      entrantId = $(this).parent("li.entrantSignup").attr("id");
      console.log("Name change fired for entrantId=%s", entrantId);
      signupData = signupDataMask;
      signupData.task = "update";

      let oldname = $(this).attr("data-oldname");
      console.log('Old name value is %s',oldname);
      signupData.name = oldname;
      signupData.newName = $(this).val();
      if (signupData.newName.length < minNameLength) {
        $(this).val(signupData.name);
        return;
      }
      console.log("New name value is %s", signupData.newName);
      $(this).attr("data-oldname",signupData.newName);//Set the old name to the new one just entered

      //Change id to match the new name so it can be found using the new name
      $(this)
        .parent("li.entrantSignup")
        .attr("id", signupData.newName.replace(/ /g, "_"));

      //Make sure we don't change the seed
      signupData.seed = -99;
      signupData.position = $(this)
        .parent("li.entrantSignup")
        .attr("data-currentPos");

      signupData.clubId = $(".signupContainer").attr("data-clubid");
      signupData.eventId = $(".signupContainer").attr("data-eventid");
      signupData.bracketName = $(".signupContainer").attr("data-bracketname");
      ajaxFun(signupData);
    });

    //Delete an entrant
    $(".eventSignup").on("click", "button.entrantDelete", function (e) {
      console.log("Delete fired!");
      entrantId = $(this).parent("li.entrantSignup").attr("id");
      console.log("entrantId=%s", entrantId);
      let name = $(this)
        .parent("li.entrantSignup")
        .children("input.entrantName")
        .val();
      console.log("name is '%s'", name);
      signupData = signupDataMask;
      signupData.name = name; //entrantId.replace(/_/g, ' ');
      signupData.task = "delete";

      signupData.clubId = $(".signupContainer").attr("data-clubid");
      signupData.eventId = $(".signupContainer").attr("data-eventid");
      signupData.bracketName = $(".signupContainer").attr("data-bracketname");

      $(this).parent("li.entrantSignup").remove();

      ajaxFun(signupData);
    });

    function isDuplicate(name) {
      console.log("isDuplicate(%s)", name);
      $find = findEntrant(name);
      return $find.length > 0;
    }

    function findEntrant(name) {
      console.log("findEntrant(%s)", name);
      test = name.trim().replace(/ /g, "_").replace(/'/g, "").replace(/&/g, "").replace(/\//,"");
      console.log("test=#li%s", test);
      $found = $("li#" + test);
      return $found;
    }

    //Add an entrant
    $("#addEntrant").on("click", function (e) {
      console.log("Add entrant fired!");
      let name = prompt("Entrant's name: ", "");
      if (null === name) return;
      if (name.length < minNameLength) return;

      name = name.replace(/b*&b*/g, " and ");
      if (isDuplicate(name)) {
        alert(name + " already exists");
        return;
      }

      let pos = $(".eventSignup").children().length + 1;
      signupData = signupDataMask;
      signupData.name = name;
      signupData.seed = 0;
      signupData.position = pos;
      signupData.task = "add";
      signupData.clubId = $(".signupContainer").attr("data-clubid");
      signupData.eventId = $(".signupContainer").attr("data-eventid");
      signupData.bracketName = $(".signupContainer").attr("data-bracketname");

      liNode = $("<li>", {
        id: name.replace(/ /g, "_").replace(/'/g, "'"),
        class: "entrantSignup sortable-container ui-state-default",
      });
      posDiv = $("<div>", {
        class: "entrantPosition ui-sortable-handle",
        html: pos + ".",
      });
      nameInput = $("<input>", {
        name: "entrantName",
        type: "text",
        maxlength: "35",
        size: "15",
        class: "entrantName",
        value: signupData.name,
      });
      seedInput = $("<input>", {
        name: "entrantSeed",
        type: "number",
        maxlength: "2",
        size: "2",
        class: "entrantSeed",
        step: "any",
        value: signupData.seed,
      });
      deleteButton = $("<button>", {
        class: "button entrantDelete",
        type: "button",
        id: "deleteEntrant",
        html: "Delete",
      });
      liNode.append(posDiv);
      liNode.append(nameInput);
      liNode.append(seedInput);
      liNode.append(deleteButton);
      $(".eventSignup").append(liNode);

      ajaxFun(signupData);
    });

    //Approve signup by scheduling preliminary rounds
    $("#createPrelimRandom").on("click", function (event) {
      console.log("Create Preliminary Round Randomly fired!");
      let clubId = $(".signupContainer").attr("data-clubid");
      let eventId = $(".signupContainer").attr("data-eventid");
      let bracketName = tennis_signupdata_obj.bracketName;

      $(this).prop("disabled", true);
      toggleButtons(1);

      ajaxFun({
        task: "createPrelimRandom",
        clubId: clubId,
        eventId: eventId,
        bracketName: bracketName,
      });
    });

    //Approve signup by scheduling preliminary rounds without Randomizing
    $("#createPrelimNoRandom").on("click", function (event) {
      console.log("Create Preliminary Round No Random fired!");
      let clubId = $(".signupContainer").attr("data-clubid");
      let eventId = $(".signupContainer").attr("data-eventid");
      let bracketName = tennis_signupdata_obj.bracketName;

      $(this).prop("disabled", true);
      toggleButtons(1);

      ajaxFun({
        task: "createPrelimNoRandom",
        clubId: clubId,
        eventId: eventId,
        bracketName: bracketName,
      });
    });

    //Resequence the signup
    $("#reseqSignup").on("click", function (event) {
      console.log("Resequence signup fired!");
      let clubId = $(".signupContainer").attr("data-clubid");
      let eventId = $(".signupContainer").attr("data-eventid");
      let bracketName = tennis_signupdata_obj.bracketName;

      $(this).prop("disabled", true);
      toggleButtons(1);

      ajaxFun({
        task: "reseqSignup",
        clubId: clubId,
        eventId: eventId,
        bracketName: bracketName,
      });
    });
    
    /***********XML File ******************************************************/
    
    $("#entrant_uploads_file").css("opacity","0")
    let bulkEntrants = []
    let uploadInput = document.getElementById('entrant_uploads_file')
    if(null !== uploadInput) {
          uploadInput.addEventListener('change', function (event) {
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
                // parsing succeeded
                console.log(xmlDoc)
                let players = xmlDoc.getElementsByTagName('player');
                console.log("players.length=%d",players.length)
                console.log(players)
                let numAddinfo = 0;
                for (i = 0; i < players.length; i++) {
                  let player = players[i];
                  //console.log(player)
                  let first = 'unknown'
                  let last = 'unknown'
                  let id = 'unknown'
                  let addinfo = ''
                  for (j = 0; j < player.children.length; j++) {
                    switch(player.children[j].nodeName) {
                      case 'firstname':
                        first = player.children[j].textContent;
                        break;
                      case 'lastname':
                        last = player.children[j].textContent;
                        break;
                      case 'id':
                        id = player.children[j].textContent;
                        break;
                      case 'additionalinfo':
                        addinfo = player.children[j].textContent;
                        ++numAddinfo
                        break;
                    }
                  }
                  // console.log("%d. %s %s - %s", id, first, last, addinfo)
                  let playerName = `${first} ${last}`
                  let newEntrant = {'position': id, 'name': playerName, 'seed':0, 'partner': addinfo}
                  bulkEntrants.push(newEntrant)
                }
                //Remove duplicates based on additional info (i.e. doubles)
                console.log("bulkEntrants.length=%d",bulkEntrants.length)
                let slimEntrants = [];
                bulkEntrants.forEach(entrant => {
                  let found = false;
                  let entpartner = entrant.partner.trim().toLowerCase();
                  for(i=0;i<slimEntrants.length;i++) {
                    let slimname = slimEntrants[i].name.trim().toLowerCase()
                    let res = slimname.indexOf(entpartner) 
                    //console.log("%d. Comparing '%s' with '%s' and res=%d",i, slimname, entpartner, res);
                    if( entpartner != '' && res > -1) {
                      //console.log(`>>>>>slim name ${slimname} matched entrant partner ${entpartner}`)
                      found = true;
                    }
                  }
                  if(!found) {
                    slimEntrants.push(entrant);
                  }
                })

                console.log("slimEntrants.length=%d",slimEntrants.length)
                signupData = {"task": '', 'name':'bulk', 'clubId': 0, 'eventId':0, 'bracketName': '', 'entrants': []}
                signupData.task = "addBulk";
                signupData.name = 'bulk'
                signupData.clubId = $(".signupContainer").attr("data-clubid");
                signupData.eventId = $(".signupContainer").attr("data-eventid");
                signupData.bracketName = $(".signupContainer").attr("data-bracketname");
                slimEntrants.forEach(element => {
                  signupData.entrants.push(element);          
                });
                console.log(signupData)
                ajaxFun(signupData);
          }
        }
        fr.readAsText(this.files[0]);
    })
    };

    /**************************************************************************/

    //toggleButtons(tennis_signupdata_obj.numPreliminary);
  });
})(jQuery);

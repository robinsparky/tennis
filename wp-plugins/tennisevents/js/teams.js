(function ($) {
  $(document).ready(function () {
    let sig = "#tennis-event-message";
    console.log("Manage Team Tennis Matches");
    console.log(tennis_draw_obj);

    var longtimeout = 60000;
    var shorttimeout = 5000;
    var timeout = shorttimeout;

    /**
     * Function to make ajax calls.
     * Needs the "action" and "security" nonce from the local object emitted from the server
     * @param {} matchData
     */
    let ajaxFun = function (matchData) {
      console.log("Draw Management: ajaxFun");
      let reqData = {
        action: tennis_draw_obj.action,
        security: tennis_draw_obj.security,
        data: matchData,
      };
      console.log("Parameters:");
      console.log(reqData);

      // Send Ajax request with data
      let jqxhr = $.ajax({
        url: tennis_draw_obj.ajaxurl,
        method: "POST",
        async: true,
        data: reqData,
        dataType: "json",
        beforeSend: function (jqxhr, settings) {
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
            //Do stuff with data...
            applyResults(res.data.returnData);
            $(sig).html(res.data.message);
          } else {
            console.log("Done but failed (res.data):");
            console.log(res.data);
            var entiremess = res.data.message;
            for (var i = 1; i < res.data.exception.errors.length; i++) {
              entiremess += " ...<br/>";
              entiremess += res.data.exception.errors[i][0] + "<br/>";
            }
            $(sig).addClass("tennis-error");
            $(sig).html(entiremess);
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          console.log("Fail: %s -->%s", textStatus, errorThrown);
          var errmess = "Fail: status='" + textStatus + "--->" + errorThrown;
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
            $(sig).hide();
          }, timeout);
        });

      return false;
    };

    /**
     * Process the results returned from the ajax call.
     * @param {*} data 
     */
    function applyResults(data) {
      data = data || [];
      console.log("applyResults:");
      console.log(data);
      switch (data.task) {
        case "getAvailableTeamPlayers":
          makeTeamScheduleSection(data);
          break;
        default:
          console.error("Unknown task: " + data.task);
          break;
      }
    }
    
    /**
     * Find the tennis match element on the page using its composite identifier.
     * It is very important that this find method is based on match identifiers and not on css class or element id.
     * @param int eventId
     * @param int bracketNum
     * @param int roundNum
     * @param int matchNum
     */
    function findMatch(eventId, bracketNum, roundNum, matchNum) {
      console.log(
        "findMatch(%d,%d,%d,%d)",
        eventId,
        bracketNum,
        roundNum,
        matchNum
      );
      let attFilter = '.item-player[data-eventid="' + eventId + '"]';
      attFilter += '[data-bracketnum="' + bracketNum + '"]';
      attFilter += '[data-roundnum="' + roundNum + '"]';
      attFilter += '[data-matchnum="' + matchNum + '"]';
      console.log("Attribute filter:", attFilter)
      let $matchElem = $(attFilter);
      return $matchElem;
    }

    /**
     * Create the UI section for assigning players to a team match.
     * @param {*} data 
     */
    function makeTeamScheduleSection( data) {
      console.log("Make team schedule section");

      $matchElem = findMatch( data.eventId, data.bracket_number, data.round_number, data.match_number);
      // console.log("Match element:");
      // console.log($matchElem);
      let week = data.round_number;
      let teamNum = data.team_number;
      let division = data.division;
      console.log(`Week=${week} Team Number=${teamNum}${division}`);

      // let top = Math.max(0, (($(window).height() - $parent.outerHeight()) / 2) + $(window).scrollTop()) + "px";
      // let left = Math.max(0, (($(window).width() - $parent.outerWidth()) / 2) + $(window).scrollLeft()) + "px";
      let top = $matchElem.offset().top + $matchElem.outerHeight(true) / 4;
      let left = $matchElem.offset().left + $matchElem.outerWidth(true)/2;
      let mySection = document.createElement('section');
      mySection.className = 'teamScheduleSection';
      //mySection.style.position = "absolute";
      // mySection.style.top = top + "px";
      // mySection.style.left = left + "px";
      // mySection.style.zIndex = 999;
      // mySection.style.display = 'block';
      // mySection.draggable = true;

      let title = document.createElement('h3');
      title.textContent = `Assign Players for Team ${teamNum}${division} - Week ${week}`;
      mySection.appendChild(title);

      let header = document.createElement('div');
      header.className = 'teamScheduleHeader';

      let closeButton = document.createElement('button');
      closeButton.id = 'closeTeamSchedule';
      closeButton.className = 'button tennis-close-team-schedule';
      closeButton.textContent = 'Close';
      closeButton.onclick = function() { $(mySection).hide(); };

      let saveButton = document.createElement('button');
      saveButton.id = 'saveTeamSchedule';
      saveButton.className = 'button tennis-save-team-schedule';
      saveButton.textContent = 'Save';  
      saveButton.onclick = function() { alert("Save not yet implemented."); };
 
      // Clear previous
      let container = document.getElementById('tennis-assignments');
      container.innerHTML = '';

      let availPlayers = JSON.parse(data.availablePlayers);
      let assignedPlayers = JSON.parse(data.assignedPlayers);
      
      // Available players 
      let availablePlayersList = document.createElement('ul');
      availablePlayersList.className = 'teamNameList available-players';
      availablePlayersList.id = `avail${teamNum}${division}`
      let strongNode = document.createElement('strong');
      strongNode.textContent = 'Available';
      availablePlayersList.appendChild(strongNode);

      let srcTeam = document.createElement('p');
      srcTeam.className = 'schedule-player-note';
      srcTeam.textContent = `----- Team ${teamNum} -----`;
      availablePlayersList.appendChild(srcTeam);
      let ctr = 0;
      // let showingSpares = false;
      let showingDivision = '';
      availPlayers.forEach( player => {
          ++ctr;
          if(player.division !== showingDivision) {
              showingDivision = player.division;
              let srcChange = document.createElement('p');
              srcChange.className = 'schedule-player-note';
              srcChange.textContent = `----- '${showingDivision}' -----`;
              availablePlayersList.appendChild(srcChange);
          }
          // if(player.isSpare === 'yes' && !showingSpares) {
          //     showingSpares = true;
          //     let srcChange = document.createElement('p');
          //     srcChange.className = 'schedule-player-note';
          //     srcChange.textContent = '----- Spares -----';
          //     availablePlayersList.appendChild(srcChange);
          // }
          let liNode = document.createElement('li');
          liNode.id = `playerDrag${player.ID}`;
          liNode.textContent = player.name;
          liNode.draggable = true;
          liNode.addEventListener('dragstart', handleDragStart);
          availablePlayersList.appendChild(liNode);
      });
      availablePlayersList.addEventListener('dragover', handleDragOver);
      availablePlayersList.addEventListener('dragend', handleDragEnd);
      availablePlayersList.addEventListener('drop', handleDrop);

      // Assigned players
      let assignedPlayersList = document.createElement('ul');
      assignedPlayersList.className = 'teamNameList assigned-players';
      assignedPlayersList.id = `assigned${teamNum}${division}`;
      let strongNode2 = document.createElement('strong');
      strongNode2.textContent = 'Assigned';
      assignedPlayersList.appendChild(strongNode2); 

      assignedPlayers.forEach( player => {
          let LiNode = document.createElement('li');
          LiNode.id = `playerDrag${player.ID}`;
          LiNode.textContent = player.name;
          LiNode.draggable = true;
          LiNode.addEventListener('dragstart', handleDragStart);
          assignedPlayersList.appendChild(LiNode);
      });
      assignedPlayersList.addEventListener('dragover', handleDragOver);
      assignedPlayersList.addEventListener('dragend', handleDragEnd);
      assignedPlayersList.addEventListener('drop', handleDrop);

      header.append(assignedPlayersList );
      header.append(availablePlayersList);
      header.appendChild(closeButton);
      header.appendChild(saveButton);
      mySection.appendChild(header);
      
      container.style.position = "absolute";
      container.style.top = top + "px";
      container.style.left = left + "px";
      container.style.zIndex = 999;
      container.style.display = 'block';
      container.draggable = true;
      container.appendChild(mySection);
    }

    function showTeamSparesSection(target) {
        console.log("Show team spares section");
        //console.log(target);
        let $target = $(target);
        let top = $target.offset().top + $target.outerHeight(true) / 4;
        let left = $target.offset().left - 50;
        let pos = { top: top, left: left, position: "absolute", "z-index": 999 };
        //console.log(pos);
        $("section.teamSparesSection").css(pos);
        $("section.teamSparesSection").draggable();
        $(".teamSparesSection").show();
    }

    function showTeamMembersSection(target) {
        console.log("Show team members");
        //console.log(target);
        let $target = $(target);
        let teamName = $target.text().trim();
        console.log("Team name: " + teamName);
        
        let re = /(\d+)([A,B])/
        let teamSymbols = re.exec(teamName)
        let team = document.querySelector(`#team${teamSymbols[0]}`)
        if(!team) return;
        //let top = $target.offset().top + $target.outerHeight(true) / 4;
        let top = $target.offset().top - 20;
        let left = $target.offset().left + $target.outerWidth(true)/2;
        let pos = { top: top, left: left, position: "absolute", "z-index": 999 };
        //console.log(pos);
        $(team).css(pos);
        $(team).draggable();
        $(team).show();
    }

    function getAvailableTeamPlayers(target) {
        console.log("Retrieve players for team");
        let $target = $(target);
        $parent = $target.parents(".item-player");
        console.log($parent);
        let eventId = $parent.attr("data-eventid");
        let bracketNum = $parent.attr("data-bracketnum");
        let roundNum = $parent.attr("data-roundnum");
        let matchNum = $parent.attr("data-matchnum");

        let teamName = $target.text().trim();
        console.log("Team name: " + teamName);
        let re = /(\d+)([A-Z])/
        let teamSymbols = re.exec(teamName)
        console.log(`#team${teamSymbols[0]}`)
        console.log(`Team Number=${teamSymbols[1]}`)
        console.log(`Division=${teamSymbols[2]}`)

        let teamData = {
            task: 'getAvailableTeamPlayers',
            eventId: eventId,
            bracket_number: bracketNum,
            round_number: roundNum,
            match_number: matchNum,
            team_number: teamSymbols[1],
            division: teamSymbols[2],
        }
        console.log("Team data:");
        console.log(teamData);
        ajaxFun(teamData);
    }
    //Add drag listeners to the given item
    function addDragListeners(item) {
      item.addEventListener('dragstart', handleDragStart);
    }

    //Handler for the start of a drag operation
    function handleDragStart(e) {
      console.log("handleDragStart"); 
      console.log(e)
      console.log(e.target.id)
      $("#saveTeamSchedule").prop("disabled", false);
      e.currentTarget.classList.add("dragging");
      e.dataTransfer.effectAllowed = 'move'; // Specify the effect (move, copy, link)
      e.dataTransfer.clearData();
      e.dataTransfer.setData('text/plain', e.target.id);
      console.log(e)

      setTimeout(() => {
        e.target.style.display.opacity = '0.4';
      }, 0);
    }

    function countMembers(container) {
        // console.log("countMembers:")
        //console.log(container)
        let countNode = container.querySelector('.team-count');
        if(!countNode) return;

        let sz = container.querySelectorAll('li').length
        let oldText = countNode.textContent
        let newText = oldText.replace(/\(\d+\)/,`(${sz})`)
        countNode.textContent = newText
    }

    function handleDragOver(e) {
      console.log("handleDragOver");
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move'; // Explicitly show the move effect
    }

    function handleDragEnd(e) {
      e.target.classList.remove("dragging");
    }

    function handleDrop(e) {
      e.preventDefault();
      // console.log("handleDrop");
      const id = e.dataTransfer.getData('text/plain');
      //console.log("id:");
      //console.log(id);
      const draggableElement = document.getElementById(id);
      //console.log("draggableElement:");
      //console.log(draggableElement);
      let srcContainer = draggableElement.closest('.available-players , .spare-players');
    
      // Append the dragged element to the target list
      // e.target is the element the item was dropped onto.
      // We need to find the actual <ul> element to append to, in case the user drops it on the container div
      let dropTarget = e.target;
      // console.log("drop target before:");
      // console.log(dropTarget);
      while (dropTarget.tagName !== 'UL') {
          dropTarget = dropTarget.parentNode;
      }
      // console.log("dropTarget: after");
      // console.log(dropTarget);

      if (dropTarget.tagName === 'UL') {
          dropTarget.appendChild(draggableElement);
          // countMembers(dropTarget)
          // countMembers(srcContainer)
      }

      //Reset visual feedback
      draggableElement.style.opacity = '1';
      
    }

    $(".homeentrant, .visitorentrant").on("click", function (e) {
        console.log("Clicked entrant");
        if(tennis_draw_obj.security === '') {
          console.error("Cannot schedule players.");
          showTeamMembersSection(e.target);
        //showTeamSparesSection(e.target);
        }
        else {
          getAvailableTeamPlayers(e.target);
        }
    });

  })//ready
})(jQuery);
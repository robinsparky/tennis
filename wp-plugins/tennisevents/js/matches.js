(function ($) {
  $(document).ready(function () {
    let sig = "#tennis-event-message";
    console.log("Manage Matches");
    console.log(tennis_draw_obj);

    var longtimeout = 60000;
    var shorttimeout = 5000;
    var winnerclass = "matchwinner";
    var MajorStatus = {
      NotStarted: 1,
      InProgress: 2,
      Completed: 3,
      Bye: 4,
      Waiting: 5,
      Cancelled: 6,
      Retired: 7,
    };
    const isString = (str) => typeof str === "string" || str instanceof String;

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
          }, shorttimeout);
        });

      return false;
    };

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
          (e.code === 22 ||
            // Firefox
            e.code === 1014 ||
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
     * Apply the response from an ajax call to the elements of the page
     * For example, recording scores or defaulting a player or adding comments
     * @param {} data
     */
    function applyResults(data) {
      data = data || [];
      console.log("Apply results:");
      console.log(data);
      if ($.isArray(data)) {
        console.log("Data is an array");
        let task = data["task"];
        switch (task) {
          case 'switchplayers':
            switchEntrants(data);
            break;
          case "recordscore":
            updateMatchdate(data);
            updateScore(data);
            break;
          case "setcomments":
            updateComments(data);
            break;
          case "advance":
            advanceMatches(data);
            break;
          case "insertAfter":
            //window.location.reload();
            updateMatchNumbers(data);
            break;
          case "undomatch":
            undoMatch(data);
            break;
          case "resetmatch":
            resetMatch(data);
            break;
          default:
            console.log("Unknown task from server(array): '%s'", task);
            break;
        }
      } else if (typeof data == "string") {
        console.log("Data is a string");
        switch (data) {
          case "createPrelim":
            $("#approveDraw").prop("disabled", false);
            $("#createPrelim").prop("disabled", true);
            $("#removePrelim").prop("disabled", false);
            $("#advanceMatches").prop("disabled", false);
            window.location.reload();
            break;
          case "reset":
            window.location = $("a.link-to-signup").attr("href");
            $("#approveDraw").prop("disabled", true);
            $("#createPrelim").prop("disabled", false);
            $("#removePrelim").prop("disabled", true);
            $("#advanceMatches").prop("disabled", true);
            //window.location.reload();
            break;
          case "approve":
            $("#approveDraw").prop("disabled", true);
            $("#createPrelim").prop("disabled", true);
            $("#removePrelim").prop("disabled", true);
            $("#advanceMatches").prop("disabled", false);
            window.location.reload();
            break;
        }
      } else {
        console.log("Data is an object");
        let task = data.task;
        switch (task) {
          case 'switchplayers':
            switchEntrants(data);
            break;
          case "savescore":
            updateScore(data);
            //advanceMatches(data);
            break;
          case "setcomments":
            updateComments(data);
            break;
          case "defaultentrant":
            updateStatus(data);
            break;
          case "setmatchstart":
            updateMatchDate(data);
            break;
          case "advance":
            advanceMatches(data);
            updateChampion(data);
            break;
          case "reset":
            window.location = $("a.link-to-signup").attr("href");
            break;
          case "insertAfter":
            //window.location.reload();
            //swapPlayers(data);
            updateMatchNumbers(data);
            break;
          case "undomatch":
            undoMatch(data);
            break;
          case "resetmatch":
            resetMatch(data);
            break;
          default:
            console.log("Unknown task from server(object): '%s'", task);
            break;
        }
      }
    }

    /**
     * Advance completed matches in the draw/schedule
     * @param {*} data
     */
    function advanceMatches(data) {
      console.log("advanceMatches");
      console.log(data);
      $("#advanceMatches").prop("disabled", false);
      let advanced = data.advanced || data;
      if (typeof advanced === "number" && advanced > 0) {
        console.log(`${advanced} players will be advanced.`);
        window.location.reload();
      }
    }

    var fromMatchNum = 0 //set on start of sorting
    $(".prelimOnly").sortable({
      items: "> tr",
      // revert: true,
      // containment: ".prelimOnly",
      handle: ".item-player",
      cursor: "move",
      // helper: "clone",
      //placeholder: "placeholderHighlight",
      tolerance: "pointer",
      placeholder: "placeholderHighlight",
      forcePlaceholderSize: true,
      // helper: "original",
      start: function(event,ui) {
        console.log("start")
        let rowEl  = ui.item[0]
        let matchEl = rowEl.firstElementChild
        fromMatchNum = matchEl.dataset.matchnum
        console.log("From Match Number:", fromMatchNum)
      },
      stop: function(event,ui) {
          console.log("stop")
          let rowEl = ui.item[0]
          //console.log("rowEl:",rowEl)
          //console.log("Previous Sibling Element:", rowEl.previousElementSibling)
          let prevMatchEl = rowEl.previousElementSibling.firstElementChild
          //console.log("Previous Match Element:", prevMatchEl)
          let eventId = prevMatchEl.dataset.eventId
          let bracketNum = prevMatchEl.dataset.bracketnum
          let roundNum = prevMatchEl.dataset.roundnum
          let toMatchNum = prevMatchEl.dataset.matchnum
          console.log("To Match Number:", toMatchNum)

          let taskData = {};
          taskData.task = "insertAfter";
          taskData.sourceRn = roundNum;
          taskData.sourceMn = fromMatchNum;
          taskData.targetMn = toMatchNum;
          taskData.clubId = tennis_draw_obj.clubId;
          taskData.eventId = tennis_draw_obj.eventId;
          taskData.bracketName = tennis_draw_obj.bracketName;
          taskData.bracketNum = bracketNum
          console.log("taskData:",taskData);
          ajaxFun(taskData);
        }
    })

    /**
     * Update the match start date and time
     * @param {*} data
     */
    function updateMatchDate(data) {
      console.log("updateMatchDate");

      let $matchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.matchNum
      );
      $matchEl
        .children(".matchstart")
        .html(data.matchdate + "<br/>" + data.matchtime);
      $matchEl.children(".matchstart").fadeIn(500);
      $matchEl.children(".changematchstart").hide();
      updateStatus(data);
    }

    /**
     * Update match numbers 
     * resulting from a insertAfter operation
     */
    function updateMatchNumbers(data) {
      console.log("updateMatchNumbers")
      console.log(data)
      let newMatchMap = data.move;
      console.log("newMatchMap",newMatchMap)
      // newMatchMap.sort((a, b) => {
      //   //Descending
      //   if (a.oldMatchNum > b.oldMatchNum) {
      //     return -1;
      //   }
      //   if (a.oldMatchNum < b.oldMatchNum) {
      //     return 1;
      //   }
      //   // a must be equal to b
      //   return 0;
      // })

      //let $allMatchElems = findAllMatches(data.eventId,data.bracketNum,1)
      let $allMatchElems = $('.item-player')
      //console.log("All match elems:", $allMatchElems)

      for(let i=0;i<$allMatchElems.length;i++){
        match = $allMatchElems.get(i)
        arrMap = newMatchMap.filter( map => { if(match.dataset.matchnum == map.oldMatchNum) return true 
                                              else return false
                                              } )
        if(arrMap.length === 1) {
          thisMap = arrMap[0]
          console.log(`Mapping from: ${thisMap.oldMatchNum} to: ${thisMap.newMatchNum}`)
          match.dataset.matchnum = thisMap.newMatchNum
          newTitle = `M(${data.eventId},${data.bracketNum},1,${thisMap.newMatchNum})`
          $(match).children('.matchinfo.matchtitle').text(newTitle)
        }
        else {
          mess = `Error when filter looking the mapping of match number '${match.dataset(matchnum)}'`
          console.log(mess)
          //throw mess
        }
      }//end for
      
    }

    /**
     * Swap player home and visitor names from one match to another
     * @param {*} data
     */
    function swapPlayers(data) {
      console.log("swapPlayers");
      console.log(data);
      let $sourceEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.swap.source.roundNum,
        data.swap.source.matchNum
      );

      let homeName = data.swap.source.home.name;
      if (data.swap.source.home.seed > 0) {
        homeName = `${data.swap.source.home.name}(${data.swap.source.home.seed})`;
      }

      let visitorName = data.swap.source.visitor.name;
      if (data.swap.source.visitor.seed > 0) {
        visitorName = `${data.swap.source.visitor.name}(${data.swap.source.visitor.seed})`;
      }
      $sourceEl.children(".homeentrant").text(homeName);
      $sourceEl.children(".visitorentrant").text(visitorName);

      console.log($sourceEl);

      let $targetEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.swap.target.roundNum,
        data.swap.target.matchNum
      );
      console.log($targetEl);
      homeName = data.swap.target.home.name;
      if (data.swap.target.home.seed > 0) {
        homeName = `${data.swap.target.home.name}(${data.swap.target.home.seed})`;
      }

      visitorName = data.swap.target.visitor.name;
      if (data.swap.target.visitor.seed > 0) {
        visitorName = `${data.swap.target.visitor.name}(${data.swap.target.visitor.seed})`;
      }

      $targetEl.children(".homeentrant").text(homeName);
      $targetEl.children(".visitorentrant").text(visitorName);

      $sourceEl.addClass("emphasize-swap");
      $targetEl.addClass("emphasize-swap");
      setTimeout(function () {
        $sourceEl.removeClass("emphasize-swap");
        $targetEl.removeClass("emphasize-swap");
      }, 3000);
    }

    /**
     * Switch players (entrants)
     * @param {*} data
     */
        function switchEntrants(data) {
          console.log("switchEntrants");
          let $sourceEl = findMatch(
            data.eventId,
            data.bracketNum,
            data.roundNum,
            data.matchNum
          );    
          $sourceEl.children(`.${data.sourceType}`).text(data.sourcePlayer);
    
          let $targetEl = findMatch(
            data.eventId,
            data.bracketNum,
            data.roundNum,
            data.targetMatchNum
          );
          $targetEl.children(`.${data.targetType}`).text(data.targetPlayer);
        }
    

    /**
     * Update the home entrant name
     * @param {*} data
     */
    function updateHome(data) {
      console.log("updateHome");
      let $matchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.matchNum
      );

      $matchEl.children(".homeentrant").text(data.homeplayer);

      //Now change the match the changed home came from
      let $switchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.switchMatchNum
      );
      $switchEl.children(".homeentrant").text(data.switchHomePlayer);
    }

    /**
     * Update the visitor entrant name
     * @param {*} data
     */
    function updateVisitor(data) {
      console.log("updateVisitor");
      let $matchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.matchNum
      );
      $matchEl.children(".visitorentrant").text(data.visitorplayer);

      //Now change the match the changed visitor came from
      let $switchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.switchMatchNum
      );
      $switchEl.children(".visitorentrant").text(data.switchVisitorPlayer);
    }

    /**
     * Update the champion if crowned
     * @param {} data
     */
    function updateChampion(data) {
      console.log("updateChampion");
      if (isString(data.advanced)) {
        //find the el and update it.
        console.log(`Updating the champion '${data.advanced}'`);
      } else {
        console.log("No champion crowned yet.");
      }
    }

    /**
     * Update the match scores
     * @param {*} data
     */
    function updateScore(data) {
      console.log("updateScore");
      console.log(data);
      let $matchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.matchNum
      );
      console.log("matchEL:");
      console.log($matchEl);
      console.log(
        "Score table: %s; status=%s",
        data.displayscores,
        data.status
      );
      $matchEl.find(".displaymatchscores").empty();
      $matchEl.find(".displaymatchscores").append(data.displayscores);
      $matchEl.find(".modifymatchscores.tennis-modify-scores").empty();
      $matchEl
        .find(".modifymatchscores.tennis-modify-scores")
        .append(data.modifyscores);
      $matchEl.find(".displaymatchscores").fadeIn(1000);
      $matchEl.find(".modifymatchscores").hide();
      $matchEl.find(".matchstatus").text(data.status);
      switch (data.winner) {
        case "home":
          $matchEl.find(".homeentrant").addClass(winnerclass);
          break;
        case "visitor":
          $matchEl.find(".visitorentrant").addClass(winnerclass);
          break;
        case "tie":
          $matchEl.find(".homeentrant").addClass(winnerclass);
          $matchEl.find(".visitorentrant").addClass(winnerclass);
          break;
        default:
          $matchEl.find(".homeentrant").removeClass(winnerclass);
          $matchEl.find(".visitorentrant").removeClass(winnerclass);
          break;
      }
      updateStatus(data);
      updateChampion(data);
    }

    /**
     * Update the results from undoing a completed match
     * @param {*} data
     */
    function undoMatch(data) {
      console.log("undoMatch");
      console.log(data);
      updateScore(data);
    }
    /**
     * Update the results from undoing a completed match
     * @param {*} data
     */
    function resetMatch(data) {
      console.log("resetMatch");
      console.log(data);
      updateScore(data);
      updateMatchDate(data);
      updateHome(data);
      updateVisitor(data);
    }

    /**
     * Update the match status
     * @param {*} data
     */
    function updateStatus(data) {
      console.log("updateStatus");
      console.log(data);
      console.log(data.status);
      let $matchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.matchNum
      );
      $matchEl.attr({
        "data-majorstatus": data.majorStatus,
        "data-minorstatus": data.minorStatus,
      });
      $matchEl.find(".matchinfo.matchstatus").text(data.status);

      updateEntrantSummary(data);

      // if( typeof data['advanced'] != 'undefined' && data['advanced'] > 0 ) {
      //     alert("Reloading");
      //     window.location.reload();
      // }
    }

    /**
     * Update the entrant summary table
     * Applies to round robins only
     * @param {*} data
     */
    function updateEntrantSummary(data) {
      if (data.entrantSummary) {
        console.log("updateEntrantSummary");
        //console.log(data.entrantSummary);
        $parent = $("table.tennis-score-summary");
        for (entrant of data.entrantSummary) {
          //console.log(entrant);
          $entRow = $parent.find(
            "tr.entrant-match-summary[data-entrant='" + entrant.position + "']"
          );
          //console.log($entRow);
          n1 = $entRow.children("td.entrant-name").text();
          console.log(
            "entrant '%s' compares with html: '%s'",
            entrant.name,
            n1
          );
          $entRow.children("td.points").each(function (i, el) {
            $(el).text(entrant.totalPoints);
          });
          $entRow.children("td.games").each(function (i, el) {
            $(el).text(entrant.totalGames);
          });
          $entRow.children("td.matcheswon").each(function (i, el) {
            r = i + 1;
            $(el).text(entrant[r]);
          });
        }
      }
      if (data.bracketSummary) {
        console.log("Bracket Summary");
        //console.log(data.bracketSummary);
        let $parent = $("table.tennis-score-summary");
        let $summaryFooter = $parent.find("#tennis-summary-foot");
        let object = data.bracketSummary["byRound"];
        for (prop in object) {
          //console.log(`${prop}: ${object[prop]}`);
          $summaryFooter.find("#summary-by-round-" + prop).text(object[prop]);
        }
        $summaryFooter.find("#bracket-summary").empty();
        mycontent = `${data.bracketSummary["completedMatches"]} of ${data.bracketSummary["totalMatches"]} Matches Completed`;
        $summaryFooter.find("#bracket-summary").text(mycontent);
      }
    }

    /**
     * Update the comments for a given match
     * @param {*} data
     */
    function updateComments(data) {
      console.log("updateComments");
      console.log(data);
      let $matchEl = findMatch(
        data.eventId,
        data.bracketNum,
        data.roundNum,
        data.matchNum
      );
      console.log($matchEl.children());
      $matchEl.find(".matchcomments").text(data.comments);
    }

    /**
     * Find the match element on the page using its composite identifier.
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
     * Find the match element on the page using its composite identifier.
     * It is very important that this find method is based on match identifiers and not on css class or element id.
     * @param int eventId
     * @param int bracketNum
     * @param int roundNum
     */
     function findAllMatches(eventId, bracketNum, roundNum) {
      console.log(
        "findAllMatches(%d,%d,%d,%d)",
        eventId,
        bracketNum,
        roundNum,
        matchNum
      );
      let attFilter = '.item-player[data-eventid="' + eventId + '"]';
      attFilter += '[data-bracketnum="' + bracketNum + '"]';
      attFilter += '[data-roundnum="' + roundNum + '"]';
      console.log("Attribute filter:", attFilter)
      let $allMatchElems = $(attFilter);
      return $allMatchElems;
    }

    /**
     * Determine if a match is ready for scoring by checking status
     * @param element el The match element from the current document
     */
    function matchIsReady(el) {
      let $parent = $(el).parents(".item-player");
      let majorStatus = $parent.attr("data-majorstatus");
      if (
        majorStatus &&
        (majorStatus == MajorStatus.NotStarted ||
          majorStatus == MajorStatus.InProgress)
      ) {
        return true;
      }
      return false;
    }

    /**
     * Get all match data from the element/obj
     * @param element el Assumes that el is descendant of .item-player
     */
    function getMatchData(el) {
      let parent = $(el).parents(".item-player");
      if (parent.length == 0) return {};

      let home = parent
        .children(".homeentrant")
        .text()
        .replace("1. ", "")
        .replace(/\(.*\)/, "");
      let visitor = parent
        .children(".visitorentrant")
        .text()
        .replace("2. ", "")
        .replace(/\(.*\)/, "");
      let status = parent.children(".matchstatus").text();
      let comments = parent.children(".matchcomments").text();
      let eventId = parent.attr("data-eventid");
      let bracketNum = parent.attr("data-bracketnum");
      let roundNum = parent.attr("data-roundnum");
      let matchNum = parent.attr("data-matchnum");

      let $matchDate = parent.find("input[name=matchStartDate]");
      let matchDate = $matchDate.val();
      let $matchTime = parent.find("input[name=matchStartTime]");
      let matchTime = $matchTime.val();

      //NOTE: these jquery objects should always have non-empty arrays of equal length
      let $homeGames = parent.find("input[name=homeGames]");
      let $homeTB = parent.find("input[name=homeTieBreak]");
      let $visitorGames = parent.find("input[name=visitorGames]");
      let $visitorTB = parent.find("input[name=visitorTieBreak]");

      let scores = [];
      for (let i = 1; i <= tennis_draw_obj.numSets; i++) {
        homeG =
          typeof $homeGames[i - 1] === "undefined"
            ? 0
            : $homeGames[i - 1].value;
        homeT =
          typeof $homeTB[i - 1] === "undefined" ? 0 : $homeTB[i - 1].value;
        visitorG =
          typeof $visitorGames[i - 1] === "undefined"
            ? 0
            : $visitorGames[i - 1].value;
        visitorT =
          typeof $visitorTB[i - 1] === "undefined"
            ? 0
            : $visitorTB[i - 1].value;
        scores.push({
          setNum: i,
          homeGames: homeG,
          visitorGames: visitorG,
          homeTieBreaker: homeT,
          visitorTieBreaker: visitorT,
        });
      }

      let data = {
        eventid: eventId,
        bracketnum: bracketNum,
        roundnum: roundNum,
        matchnum: matchNum,
        home: home,
        visitor: visitor,
        status: status,
        comments: comments,
        matchdate: matchDate,
        matchtime: matchTime,
        score: scores,
      };
      console.log("getMatchData....");
      console.log(data);
      return data;
    }

    /* -------------------- Menu Visibility ------------------------------ */
    /**
     * Hide the menu
     * @param {*} event
     */
    function hideMenu(event) {
      console.log("hide menu....");
      $(".matchaction.approved").hide();
      $(".matchaction.unapproved").hide();
      $(".matchaction.completed").hide();
    }

    /**
     * Click on the menu icon to open the menu
     */
    $(".menu-icon").on("click", function (event) {
      console.log("show menu....");
      // console.log(this);
      // console.log(event.target);
      // console.log(event);
      let $parent = $(this).parents(".item-player");
      console.log($parent);
      let majorStatus = parseInt($parent.attr("data-majorstatus"));
      let minorStatus = parseInt($parent.attr("data-minorstatus"));
      console.log("major=%d, minor=%d", majorStatus, minorStatus);

      if (parseInt(tennis_draw_obj.isBracketApproved) > 0) {
        if (
          $(event.target).hasClass("menu-icon") ||
          $(event.target).hasClass("dots") ||
          $(event.target).hasClass("bar1") ||
          $(event.target).hasClass("bar2") ||
          $(event.target).hasClass("bar3") ||
          $(event.target).hasClass("bar4") ||
          $(event.target).hasClass("bar5")
        ) {
          if (
            majorStatus == MajorStatus.Completed || majorStatus == MajorStatus.Retired
          ) {
            $(this).children(".matchaction.completed").show();
          } else {
            $(this).children(".matchaction.approved").css("z-index", 999);
            $(this).children(".matchaction.approved").show();
          }
        }
      } else {
        if (
          $(event.target).hasClass("menu-icon") ||
          $(event.target).hasClass("dots") ||
          $(event.target).hasClass("bar1") ||
          $(event.target).hasClass("bar2") ||
          $(event.target).hasClass("bar3") ||
          $(event.target).hasClass("bar4") ||
          $(event.target).hasClass("bar5")
        ) {
          $(this).children(".matchaction.unapproved").css("z-index", 999);
          $(this).children(".matchaction.unapproved").show();
        }
      }
      event.preventDefault();
    });

    /**
     * Support clicking away from the menu to close it
     */
    $("body").on("click", function (event) {
      if (
        !$(event.target).hasClass("menu-icon") &&
        !$(event.target).parents().hasClass("menu-icon") &&
        !$(event.target).hasClass("modifymatchscores")
      ) {
        $(".matchaction").hide();
        $("modifymatchscores").hide();
        $("modifymatchscores").children().hide();
      }
    });

    /* ------------------------------Menu Actions ---------------------------------*/

    
    /**
     * Switch player from source match with player from any other match
     *  Can only be done if bracket is not yet approved
     */
    $(".switchplayers").on("click", function (event) {
      console.log("switch players");
      console.log(this);
      hideMenu(event);
      let sourcematch = getMatchData(this);
      let source = prompt("Please enter name of name entrant in this match", sourcematch.home + ' or ' + sourcematch.visitor );
      if (null == source) {
        console.log('source is null')
        return;
      }
      let target = prompt("Please enter name of name entrant to switch with", '' );
      if (null == target  ) {
        console.log('target is null')
        return;
      }
      if( source === target || target === sourcematch.home || target === sourcematch.visitor ) {
        console.log(`source: ${source} target: ${target}`)
        return;
      }

      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;

      console.log('calling ajax function....')
      ajaxFun({
        task: "switchplayers",
        eventId: eventId,
        bracketNum: sourcematch.bracketnum,
        roundNum: sourcematch.roundnum,
        matchNum: sourcematch.matchnum,
        bracketName: bracketName,
        sourceplayer: source,
        targetplayer: target,
      });
      console.log("called ajax function ...")
    });

    /**
     * Default the home entrant/player
     */
    $(".defaulthome").on("click", function (event) {
      console.log("default home");
      hideMenu(event);

      if (!matchIsReady(this)) {
        alert("Match is not ready for scoring.");
        return;
      }
      let matchdata = getMatchData(this);
      let comments = prompt(
        "Please enter reason for defaulting home entrant",
        matchdata.comments
      );
      if (null == comments) {
        return;
      }

      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({
        task: "defaultentrant",
        eventId: eventId,
        bracketNum: matchdata.bracketnum,
        roundNum: matchdata.roundnum,
        matchNum: matchdata.matchnum,
        bracketName: bracketName,
        player: "home",
        comments: comments,
      });
    });

    /**
     * Default the visitor entrant/player
     */
    $(".defaultvisitor").on("click", function (event) {
      console.log("default visitor");
      console.log(this);
      hideMenu(event);

      if (!matchIsReady(this)) {
        alert("Match is not read for scoring.");
        return;
      }

      let matchdata = getMatchData(this);
      let comments = prompt(
        "Please enter reason for defaulting visitor entrant",
        matchdata.comments
      );
      if (null == comments) {
        return;
      }
      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({
        task: "defaultentrant",
        eventId: eventId,
        bracketNum: matchdata.bracketnum,
        roundNum: matchdata.roundnum,
        matchNum: matchdata.matchnum,
        bracketName: bracketName,
        player: "visitor",
        comments: comments,
      });
    });

    /**
     * Record the match scores
     */
    $(".recordscore").on("click", function (event) {
      console.log("record score click");
      //console.log(this);
      hideMenu(event);
      if (!matchIsReady(this)) {
        alert("Match is not ready for scoring.");
        return;
      }

      $parent = $(this).parents(".item-player");
      // let top = Math.max(0, (($(window).height() - $parent.outerHeight()) / 2) + $(window).scrollTop()) + "px";
      // let left = Math.max(0, (($(window).width() - $parent.outerWidth()) / 2) + $(window).scrollLeft()) + "px";

      let top = $parent.offset().top + $parent.outerHeight(true) / 4;
      let left = $parent.offset().left - 50;
      let pos = { top: top, left: left, position: "absolute", "z-index": 999 };
      //console.log(pos);
      $parent.find("div.modifymatchscores.tennis-modify-scores").css(pos);
      $parent.find("div.modifymatchscores.tennis-modify-scores").draggable();
      showModifyScores(this);
    });

    $(".undomatch").on("click", function (event) {
      console.log("undo match click");
      console.log(this);
      hideMenu(event);
      $parent = $(this).parents(".item-player");
      let matchdata = getMatchData(this);
      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({
        task: "undomatch",
        eventId: eventId,
        bracketNum: matchdata.bracketnum,
        roundNum: matchdata.roundnum,
        matchNum: matchdata.matchnum,
        bracketName: bracketName,
      });
    });

    $(".resetmatch").on("click", function (event) {
      console.log("reset match click");
      console.log(this);
      hideMenu(event);
      $parent = $(this).parents(".item-player");
      let matchdata = getMatchData(this);
      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({
        task: "resetmatch",
        eventId: eventId,
        bracketNum: matchdata.bracketnum,
        roundNum: matchdata.roundnum,
        matchNum: matchdata.matchnum,
        bracketName: bracketName,
      });
    });

    /**
     * Show Modify scores elements
     * @param element obj
     */
    function showModifyScores(obj) {
      let $parent = $(obj).parents(".item-player");
      //$parent.find('.displaymatchscores').hide();
      $parent.find(".modifymatchscores").fadeIn(1000);
    }

    /**
     * Hide the score modification elements
     * @param element obj
     */
    function hideModifyScores(obj) {
      if (obj) {
        let $parent = $(obj).parents(".item-player");
        $parent.find(".modifymatchscores").hide();
        $parent.find(".displaymatchscores").fadeIn(1000);
      } else {
        $(".modifymatchscores").hide();
        $(".displaymatchscores").show();
      }
    }

    /**
     * Cancel the changing of match scores
     */
    $(".modifymatchscores.tennis-modify-scores").on(
      "click",
      ".cancelmatchscores",
      function (event) {
        console.log("cancel scores");
        console.log(event.target);
        hideModifyScores(this);
      }
    );

    /**
     * Save the recorded match score
     */
    $(".modifymatchscores.tennis-modify-scores").on(
      "click",
      ".savematchscores",
      function (event) {
        console.log("save scores");
        let matchdata = getMatchData(this);
        let $parent = $(this).parents(".item-player");
        $tohide = $parent.find(".modifymatchscores");
        $tohide.hide();
        $tohide.children().hide();
        $parent
          .find(".displaymatchscores")
          .html('<span class="tennis-message">one moment...</span>')
          .show();

        let eventId = tennis_draw_obj.eventId;
        let bracketName = tennis_draw_obj.bracketName;
        ajaxFun({
          task: "savescore",
          eventId: eventId,
          bracketNum: matchdata.bracketnum,
          roundNum: matchdata.roundnum,
          matchNum: matchdata.matchnum,
          bracketName: bracketName,
          score: matchdata.score,
        });
      }
    );

    /**
     * Capture start date & time of the match
     */
    $(".setmatchstart").on("click", function (event) {
      console.log("match start");
      console.log(this);
      hideMenu(event);
      let $parent = $(this).parents(".item-player");
      $parent.find(".matchstart").hide();
      $parent.find(".changematchstart").fadeIn(500);
    });

    /**
     * Cancel the setting of match start date/time
     */
    $(".cancelmatchstart").on("click", function (event) {
      console.log("cancel scores");

      let $parent = $(this).parents(".item-player");
      $parent.find(".changematchstart").hide();
      $parent.find(".matchstart").fadeIn(500);
    });

    /**
     * Save start date & time of the match
     */
    $(".savematchstart").on("click", function (event) {
      console.log("match start");
      console.log(this);
      let matchdata = getMatchData(this);

      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({
        task: "setmatchstart",
        eventId: eventId,
        bracketNum: matchdata.bracketnum,
        roundNum: matchdata.roundnum,
        matchNum: matchdata.matchnum,
        bracketName: bracketName,
        matchdate: matchdata.matchdate || "",
        matchtime: matchdata.matchtime || 0,
      });
    });

    /**
     * Capture comments regarding the match
     */
    $(".setcomments").on("click", function (event) {
      console.log("set comments");
      console.log(this);
      hideMenu(event);
      let matchdata = getMatchData(this);
      let comments = prompt("Please enter comments", matchdata.comments);
      if (null == comments) {
        return;
      }

      if (comments == "remove") {
        comments = "";
      }

      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({
        task: "setcomments",
        eventId: eventId,
        bracketNum: matchdata.bracketnum,
        roundNum: matchdata.roundnum,
        matchNum: matchdata.matchnum,
        bracketName: bracketName,
        comments: comments,
      });
    });

    /* ------------------------Button Actions -------------------------------------- */

    /**
     * Approve draw
     */
    $("#approveDraw").on("click", function (event) {
      console.log("Approve draw fired!");

      $(this).prop("disabled", true);
      let eventId = tennis_draw_obj.eventId; //$('.bracketdraw').attr('data-eventid');
      let bracketName = tennis_draw_obj.bracketName; //$('.bracketdraw').attr('data-bracketname');
      ajaxFun({ task: "approve", eventId: eventId, bracketName: bracketName });
    });

    /**
     * Advance completed matches
     */
    $("#advanceMatches").on("click", function (event) {
      console.log("advanceMatches fired!");

      let ans = confirm("Are you sure you want to advance the matches?");
      if (false === ans) return;

      $(this).prop("disabled", true);
      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;
      ajaxFun({ task: "advance", eventId: eventId, bracketName: bracketName });
    });

    /**
     * Remove preliminary rounds
     */
    $("#removePrelim").on("click", function (event) {
      console.log("Remove preliminary round fired!");
      let ans = confirm("Are you sure?");
      if (ans != true) return;

      let eventId = tennis_draw_obj.eventId;
      let bracketName = tennis_draw_obj.bracketName;

      $(this).prop("disabled", true);
      ajaxFun({ task: "reset", eventId: eventId, bracketName: bracketName });
    });

    hideModifyScores();

    // maxPos = getMaxPosition();
    // console.log("Max position = %d", maxPos);

    //Test
    if (storageAvailable("localStorage")) {
      console.log("Yippee! We can use localStorage awesomeness");
    } else {
      console.log("Too bad, no localStorage for us");
    }
  });
})(jQuery);

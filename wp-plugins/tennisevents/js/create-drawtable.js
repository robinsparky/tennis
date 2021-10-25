function createTable(bracket) {
  mytable = $("<table></table>").attr({
    id: "basicTable",
    class: "table table-hover",
  });
  var rows = new Number($("#rowcount").val());
  var cols = new Number($("#columncount").val());
  var tr = [];

  for (var i = 0; i <= rows; i++) {
    var row = $("<tr></tr>")
      .attr({ class: ["class1"].join(" ") })
      .appendTo(mytable);
    if (i == 0) {
      for (var j = 0; j < cols; j++) {
        $("<th></th>")
          .text("text1")
          .attr({ class: ["info"] })
          .appendTo(row);
      }
    } else {
      for (var j = 0; j < cols; j++) {
        $("<td></td>").text("text1").appendTo(row);
      }
    }
  }

  mytable.appendTo("#box");
}

function generateTable(rowsData, titles, type, _class) {
  var $table = $("<table>").addClass(_class);
  var $tbody = $("<tbody>").appendTo($table);

  if (type == 2) {
    //vertical table
    if (rowsData.length !== titles.length) {
      console.error("rows and data rows count doesent match");
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
  } else if (type == 1) {
    //horsantal table
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
      console.error("rows and data rows count do not match");
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

function createScoreInputTable(home, visitor, numSets) {
  let $table = $("<table>").addClass("tablematchscores");
  let $caption = $("<caption>");
  $caption.html("Match Scores");
  $caption.appendTo($table);

  let $tbody = $("<tbody>").appendTo($table);

  let $tr = $("<tr>");
  $("<th>").html("Entrant").appendTo($tr);
  for (i = 1; i <= numSets; i++) {
    $("<th>")
      .html("Set" + i)
      .appendTo($tr);
  }
  $tr.appendTo($tbody);

  let $trHome = $("<tr>").attr("id", home);
  let $tdHome = $("<td>").html(home);
  $tdHome.appendTo($trHome);
  for (i = 1; i <= numSets; i++) {
    $("<td>").html("").appendTo($trHome);
  }
  $trHome.appendTo($tbody);

  let $trVisitor = $("<tr>").attr("id", visitor);
  let $tdVisitor = $("<td>").html(visitor);
  $tdVisitor.appendTo($trVisitor);
  for (i = 1; i <= numSets; i++) {
    $("<td>").html("").appendTo($trVisitor);
  }
  $trVisitor.appendTo($tbody);

  return $table;
}

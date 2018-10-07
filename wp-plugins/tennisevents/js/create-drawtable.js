function createTable( bracket ) {
    mytable = $('<table></table>').attr({ id: "basicTable",class:"table table-hover"});
        var rows = new Number($("#rowcount").val());
        var cols = new Number($("#columncount").val());
        var tr = [];
    
        for (var i = 0; i <= rows; i++) {
            var row = $('<tr></tr>').attr({ class: ["class1"].join(' ') }).appendTo(mytable);
            if (i == 0) {
            for (var j = 0; j < cols; j++) {
                    $('<th></th>').text("text1").attr({class:["info"]}).appendTo(row);
                }
            }else {
                    for (var j = 0; j < cols; j++) {
                        $('<td></td>').text("text1").appendTo(row);
                    }
            }
        }
    
        mytable.appendTo("#box");
}
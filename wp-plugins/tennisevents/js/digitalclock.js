(function ($) {
  $(document).ready(function () {
    console.log("Digital Clock");

    function formatDate(date) {
      var d = date ? new Date(date) : new Date(),
        month = "" + (d.getMonth() + 1),
        day = "" + d.getDate(),
        year = d.getFullYear();

      if (month.length < 2) month = "0" + month;
      if (day.length < 2) day = "0" + day;
      let res = [year, month, day].join("-");
      //res = d.toDateString();
      let ampm = d.getHours() >= 12 ? " pm" : " am";
      let hours = d.getHours() <= 12 ? d.getHours() : d.getHours() - 12;
      res =
        res +
        " - " +
        hours +
        ":" +
        d.getMinutes() +
        ":" +
        d.getSeconds() +
        ":" +
        ampm;

      //return [year, month, day].join("-");
      return res;
    }

    function displayDigitalClock() {
      let dtString = Date();
      document.getElementById("digiclock").innerHTML = formatDate(dtString);
    }

    setInterval(displayDigitalClock, 1000);
  });
})(jQuery);

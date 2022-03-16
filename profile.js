

module.exports.loadProfile= datProfileFunctions;
module.exports.testCountdown=testCountdown;

let flatpickr=require('flatpickr');
var countdownTimer;


function datProfileFunctions($=jQuery){
  const profileFP= flatpickr('.profile-dat-date', {
                      altInput: true,
                      altFormat: "F j, Y",
                      dateFormat: "Ymd"
                  });

  $('#datProfileWidget .countdown').click(function(e){
      e.preventDefault();
      profileFP.toggle();
    });

  $('body').on('click', '.flatpickr-months', function(e){
      e.stopPropagation();
    });

    $('#profile_test_date').change(function(e){
      let dateObj=profileFP.selectedDates[0]
      let unixStamp=dateObj.valueOf();
      $('#testDate').attr('data-date', `${unixStamp/1000}`);
      testCountdown($);

      let normalMonth=dateObj.getMonth()+1
      $.ajax({
          url: datGlobal.ajaxurl,
          type: 'post',
          data: {
              testDate: Math.floor(unixStamp/1000),
              action: 'dat_update_dat_test',
              security: datGlobal.markNonce
          }
        });
    });

}

/**
 * Add countdown to user profile widget
 */
function testCountdown($ = jQuery) {
    let datTestDate = document.getElementById('testDate');
    var date = $(datTestDate).attr('data-date')
    if (date != null) {
        var countDownDate = new Date(date * 1000).getTime();
        // Update the count down every 1 second
        if (countdownTimer != null) {
            clearInterval(countdownTimer);
        }
        countdownTimer = setInterval(function () {
            // Get today's date and time
            var now = new Date().getTime();

            // Find the distance between now and the count down date
            var distance = countDownDate - now;

            // Time calculations for days, hours, minutes and seconds
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

            datTestDate.innerHTML = days + "d " + hours + "h "
                + minutes + "m ";

            if (distance < 0) {
                clearInterval(this);
                datTestDate.innerHTML = "EXPIRED";
            }
        }, 1000);
    }
}

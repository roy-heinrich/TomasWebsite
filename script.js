
$(window).on("scroll load", function() {
  var scrollTop = $(window).scrollTop();
  if (scrollTop >= 100) {
    $('body').addClass('fixed-header');
  } else {
    $('body').removeClass('fixed-header');
  }
});
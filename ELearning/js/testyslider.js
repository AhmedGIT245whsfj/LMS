(function () {
  "use strict";
  if (!window.jQuery) return;
  var $ = window.jQuery;
  $(function () {
    if (!$.fn || typeof $.fn.owlCarousel !== "function") {
      return;
    }
    try {
      if ($.fn.owlCarousel) { $(".owl-carousel").owlCarousel();
    } catch (e) {
      console.log("owlCarousel init error:", e);
    }
  });
})();
}

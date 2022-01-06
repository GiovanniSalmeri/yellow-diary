/* Diary extension, https://github.com/GiovanniSalmeri/yellow-diary */

"use strict";
document.addEventListener("DOMContentLoaded", function() {
    var links = document.getElementsByClassName("popup");
    for (var i = 0; i < links.length; i++) {
        links[i].addEventListener("click", popupHandler);
    }
});
// see https://www.sitepoint.com/social-media-button-links/
var popupHandler = popupHandler || function(e) {
    e = e || window.event;
    var t = e.target || e.srcElement;
    var popup = window.open(t.href, "_blank", "width=600, height=450, left=0, top=0, menubar=0, toolbar=0, status=0");
    if (popup) {
        if (popup.focus) popup.focus();
        if (e.preventDefault) e.preventDefault();
        e.returnValue = false;
    }
    return !!popup;
}

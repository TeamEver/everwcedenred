$ = jQuery.noConflict();
$(document).ready(function(e){
	$('form[name="checkout"] input').each(function(){
		var edenred_val = readCookie(
			$(this).attr('name')
		);
		if (edenred_val != null) {
			$(this).val(edenred_val);
		}
	});
	$('form[name="checkout"] input').on('change', function() {
		eraseCookie(
			$(this).attr('name')
		);
		createCookie(
			$(this).attr('name'),
			$(this).val()
		);
	});
});
// Cookies
function createCookie(name, value, days) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toGMTString();
    }
    else var expires = "";               

    document.cookie = name + "=" + value + expires + "; path=/";
}
function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}
function eraseCookie(name) {
    createCookie(name, "", -1);
}
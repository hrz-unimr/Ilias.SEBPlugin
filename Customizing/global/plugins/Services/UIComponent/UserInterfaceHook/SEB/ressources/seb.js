function seb_init() {
	addUser();
}

function addUser() {
	//alert(seb_object.user.matriculation);
	logout = "logout";
	$("header div.row").append("<div class=\"sebObject\"><span class=\"sebFullname\">"+seb_object.user.firstname + " " + seb_object.user.lastname + "</span><span class=\"sebLogin\"> (" + seb_object.user.login + ")</span> >> " + logout + "</div>");
	// cut logout
	//var logout = $('.ilLogin a').wrapAll('<a></a>').parent().html();
	// build new html
	//$('.ilLogin').html("<span class=\"sebFullname\">"+seb_object.user.firstname + " " + seb_object.user.lastname + "</span><span class=\"sebLogin\"> (" + seb_object.user.login + ")</span> >> " + logout);
}

window.addEventListener("load", seb_init, false);



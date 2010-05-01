// Javascript for team.php
// Dayan Paez
// October 27, 2008

// Disable/enable second parameter (id) depending on the
// emptiness of the value of the first parameter (object)
function allow(ob1, id2) {
    if ( ob1.value == "" ) {
	document.getElementById(id2).disabled = false;
    }
    else {
	document.getElementById(id2).disabled = true;
    }
}
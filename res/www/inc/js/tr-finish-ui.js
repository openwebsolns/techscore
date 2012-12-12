/* Hide the "Choose race by round" */

function hideTeamFinish(evt) {
    var f = document.getElementById('round_form');
    if (!f)
	return;

    var div = f.parentNode;
    div.classList.add('collapsed');
    div.onclick = function(evt) {
	div.classList.toggle('collapsed');
    };
}

// onload function
var old = window.onload;
if (old) {
    window.onload = function(evt) {
	old(evt);
	hideTeamFinish(evt);
    };
}
else
    window.onload = hideTeamFinish;

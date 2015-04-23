var TL = null;
var TL_INPUTS = Array();
function addTeamToRound(id) {
  if (!TL) {
    TL = document.getElementById("teams-list");
    if (!TL)
      return;
    var inputs = TL.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].type == "text")
        TL_INPUTS.push(inputs[i]);
    }
  }
  var elem = document.getElementById(id);
  if (!elem || elem.value != "")
    return;
  var max = 0;
  for (var i = 0; i < TL_INPUTS.length; i++) {
    var num = Number(TL_INPUTS[i].value);
    if (num > max)
      max = num;
  }
  elem.value = (max + 1);
}

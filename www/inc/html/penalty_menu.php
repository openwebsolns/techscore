<?php
echo '
<!-- Menu specific for penalties -->
<div id="penaltymenu" class="menu">
  <h3>Penalty</h3>
  <ul>';
if (empty($_SESSION['REG']['finalized']) ) {
  echo '
    <li><a href="#addpenalty">Race penalty</a></li>
    <li><a href="#addteam">Team penalty</a></li>';
}
echo '
    <li><a href="#current">Existing</a></li>
  </ul>
</div>

<hr class="hidden"/>';
?>
<!-- Menu Specific for Regattas -->
<div id="regattamenu" class="menu">
   <h3><span class="hidden">Main menu</span>&nbsp;</h3>
  <h4>Regatta</h4>
  <ul id="homelist">
    <li><a href="regatta.php?reg=<?php echo $_SESSION['REG']['id'];?>">Home</a></li>
    <li><a href="race.php?reg=<?php echo $_SESSION['REG']['id'];?>">Races</a></li>
    <li><!-- <a href="report.php?reg=<?php echo $_SESSION['REG']['id'];?>"> -->Reports<!-- </a> --></li>
    <li><a href="finalize.php?reg=<?php echo $_SESSION['REG']['id'];?>">Finalize</a></li>
  </ul>

  <h4>Schools</h4>
  <ul id="schoollist">
    <li><a href="invite.php?reg=<?php echo $_SESSION['REG']['id'];?>">Invites</a></li>
    <li><a href="team.php?reg=<?php echo $_SESSION['REG']['id'];?>">Sign-in Teams</a></li>
  </ul>

  <h4>RP Forms</h4>
  <ul id="rplist">
    <li><a href="rp.php?reg=<?php echo $_SESSION['REG']['id'];?>">RP Forms</a></li>
    <li><a href="sailor.php?reg=<?php echo $_SESSION['REG']['id'];?>">Sailors</a></li>
  </ul>

  <h4>Rotations</h4>
  <ul id="rotationlist">
    <li><a href="sail.php?reg=<?php echo $_SESSION['REG']['id'];?>">Setup</a></li>
    <li><strong><a href="rotation.php?reg=<?php echo $_SESSION['REG']['id'];?>">View/print</a></strong></li>
  </ul>

  <h4>Scores</h4>
  <ul id="finishlist">
    <li><a href="finish.php?reg=<?php echo $_SESSION['REG']['id'];?>">Finishes</a></li>
    <li><a href="penalty.php?reg=<?php echo $_SESSION['REG']['id'];?>">Penalties</a></li>
    <li><strong><a href="score.php?reg=<?php echo $_SESSION['REG']['id'];?>">Results</a></strong></li>
  </ul>
</div>

<hr class="hidden"/>

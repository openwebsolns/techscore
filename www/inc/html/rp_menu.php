<!-- Menu specific for RP forms -->
<div id="rpmenu" class="menu">
  <h3>RP</h3>
  <ul>
   <?php 
   if (empty($_SESSION['REG']['finalized']) ) {
     ?>
    <li><a href="#add">Add RP</a></li>
    <li><a href="#templist">Temporary List</a></li>
     <?php
   } 
   ?>
  </ul>
</div>

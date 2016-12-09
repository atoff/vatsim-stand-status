<?php

use CobaltGrid\StandStatus;

require_once '../vendor/autoload.php';

$StandStatus = new StandStatus("EGKK", dirname(__FILE__) . "/standData/egkkstands.csv");



?>
<table border="1">
  <tr>
    <th>Stand</th>
    <th>Occupied</th>
  </tr>
  <?php
  foreach($StandStatus->occupiedStands as $stand){
    ?>
    <tr>
      <td><?php echo $stand ?></td>
      <td><?php echo $StandStatus->stands[$stand]['occupied']['callsign'] ?></td>
    </tr>
    <?php
  }

   ?>
</table>


<table border="1">
  <tr>
    <th>Stand</th>
    <th>Lat</th>
    <th>Long</th>
  </tr>
<?php
foreach ($StandStatus->stands as $stand) {

 ?>
<tr>
  <td><?php echo $stand['id'] ?></td>
  <td><?php echo $stand['latcoord'] ?></td>
  <td><?php echo $stand['longcoord'] ?></td>
  <td><?php
  if (isset($stand['occupied']['callsign'])){
    echo $stand['occupied']['callsign'];
  }
  ?></td>
</tr>
 <?php

}
  ?>
</table>

<div>
<ul>
  <?php
    foreach($StandStatus->aircraftSearchResults as $pilot){
      echo "<li>" . $pilot['callsign'] . "</li> " . $pilot['latitude'] . " BY " . $pilot['longitude'];
    }
   ?>
</ul>
</div>

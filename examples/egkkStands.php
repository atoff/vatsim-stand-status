<?php

use CobaltGrid\VatsimStandStatus\StandStatus;

require_once '../vendor/autoload.php';

$StandStatus = new StandStatus("EGKK", dirname(__FILE__) . "/standData/egkkstands.csv", 51.148056, -0.190278);



?>

<!-- GMaps & Labels -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key="></script>
<script src="js/maplabel-min.js"></script>

<script>
  $(document).ready(function(){
		var center = {lat: 51.158, lng: -0.1820};
		map = new google.maps.Map(document.getElementById('map'), {
      zoom: 14,
      mapTypeId: 'satellite',
      center: center,
      disableDefaultUI: true

    });
    <?php
    foreach($StandStatus->occupiedStands() as $stand){
      ?>
      var cityCircle = new google.maps.Circle({
          strokeColor: '#FF0000',
          strokeOpacity: 0.8,
          strokeWeight: 2,
          fillColor: '#FF0000',
          fillOpacity: 0.35,
          map: map,
          center: {lat:
            <?php
            echo $stand['occupied']['latitude'];
            ?>, lng:
            <?php
            echo $stand['occupied']['longitude'];
            ?>},
          radius: 40
        });
        var mapLabel = new MapLabel({
          text: "<?php echo $stand['occupied']['callsign'] ?>",
          position: new google.maps.LatLng(
            <?php
            echo $stand['occupied']['latitude'];
            ?>,
            <?php
            echo $stand['occupied']['longitude'];
            ?>),
          map: map,
          fontSize: 12,
          strokeWeight: 2
        });
      <?php
    }

     ?>
		var cityCircle = new google.maps.Circle({
        strokeColor: '#FF0000',
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: '#FF0000',
        fillOpacity: 0.35,
        map: map,
        center: {lat: 19.505434, lng:-98.919304},
        radius: 100000
      });

  });
</script>
<div id="map" style="height: 500px;width: 700px;"></div>


<table border="1">
  <tr>
    <th>Stand</th>
    <th>Occupied</th>
  </tr>
  <?php
  foreach($StandStatus->occupiedStands() as $stand){
    ?>
    <tr>
      <td><?php echo $stand['id'] ?></td>
      <td><?php echo $stand['occupied']['callsign'] ?></td>
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
foreach ($StandStatus->allStands() as $stand) {

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

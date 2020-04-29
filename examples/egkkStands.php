<?php

use CobaltGrid\VatsimStandStatus\StandStatus;

require_once '../vendor/autoload.php';

$StandStatus = new StandStatus(dirname(__FILE__) . "/standData/egkkstands.csv", 51.148056, -0.190278, null, StandStatus::COORD_FORMAT_CAA);
?>

<html>
<head>
    <!-- Boostrap 4 -->
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
            integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n"
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
            integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
            crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"
            integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6"
            crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
          integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

    <script src="//cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css">

    <!-- GMaps & Labels -->
    <script src="https://maps.googleapis.com/maps/api/js?key="></script>
    <script src="js/maplabel-min.js"></script>

    <!-- Map Script -->
    <script>
        $(document).ready(function () {
            var center = {lat: 51.158, lng: -0.1820};
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 14,
                mapTypeId: 'satellite',
                center: center,
                disableDefaultUI: true

            });

            <? foreach($StandStatus->occupiedStands() as $stand){ ?>
            new google.maps.Circle({
                strokeColor: '#FF0000',
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: '#FF0000',
                fillOpacity: 0.35,
                map: map,
                center: {
                    lat: <?= $stand->occupier->latitude; ?>,
                    lng: <?= $stand->occupier->longitude; ?>
                },
                radius: 40
            });
            new MapLabel({
                text: "<?= $stand->occupier->callsign ?>",
                position: new google.maps.LatLng(
                    <?= $stand->occupier->latitude;?>,
                    <?= $stand->occupier->longitude; ?>
                ),
                map: map,
                fontSize: 12,
                strokeWeight: 2
            });
            <? } ?>

            $('#standsTable').DataTable();
        });
    </script>


</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12 col-md-2 d-flex flex-column justify-content-center">
            <h5>Occupied Stands</h5>
            <table class="table table-responsive table-sm text-center align-self-center">
                <tr>
                    <th>Stand</th>
                    <th>Occupied By</th>
                </tr>
                <?php
                foreach ($StandStatus->occupiedStands() as $stand) {
                    ?>
                    <tr>
                        <td><?= $stand->getName() ?></td>
                        <td><?= $stand->occupier->callsign ?></td>
                    </tr>
                    <?php
                }

                ?>
            </table>
        </div>
        <div class="col">
            <div id="map" style="height: 500px;width: 100%;"></div>
        </div>
    </div>
    <div class="row">
        <div class="col d-flex flex-column justify-content-center">
            <h5>All Stands</h5>
            <table id="standsTable" class="table table-responsive table-sm text-center align-self-center">
                <thead>
                <tr>
                    <th>Stand</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Occupier</th>
                </tr>
                </thead>
                <tbody>
                <? foreach ($StandStatus->allStands() as $stand) { ?>
                    <tr>
                        <td><?= $stand->getName() ?></td>
                        <td><?= $stand->latitude ?></td>
                        <td><?= $stand->longitude ?></td>
                        <td><? echo $stand->isOccupied() ? $stand->occupier->callsign : null ?></td>
                    </tr>
                <? } ?>
                </tbody>
            </table>
        </div>
        <div class="col">
            <h5>Aircraft On The Ground</h5>
            <div class="row">
                <?php
                foreach ($StandStatus->getAllAircraft() as $pilot) {
                    if($pilot->onStand()){
                        echo "<div class='col-5 bg-primary m-1'>{$pilot->callsign} ({$pilot->latitude},{$pilot->longitude}) (Stand {$pilot->getStandIndex()})</div>";
                    }else{
                        echo "<div class='col-5 bg-light m-1'>{$pilot->callsign} ({$pilot->latitude},{$pilot->longitude}) (Not on stand)</div>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>

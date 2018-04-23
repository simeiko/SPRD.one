<?php
/**
 * @author Valentyn Simeiko
 */

header('Content-Type: application/json'); // JSON API

// Generate map
if(isset($_GET['get']) && $_GET['get'] === 'map') {
    if(!isset($_GET['size']) || !isset($_GET['players'])) die(); // Check that parameters are set
    if(!in_array($_GET['size'], ['s', 'm', 'l'])) die(); // Check map size
    if(!in_array($_GET['players'], [2, 3, 4])) die(); // Check amount of players

    if($_GET['size'] === 's') { $rows = 6; $columns = 6; } // Small map
    if($_GET['size'] === 'm') { $rows = 10; $columns = 10; } // Medium map
    if($_GET['size'] === 'l') { $rows = 12; $columns = 12; } // Large map

    require_once 'classes/MapGenerator.php';

    $map = new MapGenerator($rows, $columns, $_GET['players']);
    echo $map->getMap();
}
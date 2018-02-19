# SPRD.one

SPRD.one is a multiplayer online game based on <code>canvas</code> element with use of JS ES6 and PHP7+.

**Currently game is under development process.**

<h2>PHP7+ Server Side</h2>
<h3>Map Generator</h3>
Class <code>MapGenerator</code> generates maps for sprd.one game.

Map is a JSON object that represents an array with rows and cells:
```JSON
[
    [[0,0,0,0,0,0,0,0,0],[3,2,8,0,0,0,1,0,0],[0,0,8,0,0,0,0,1,0]],
    [[3,4,8,0,0,1,0,1,0],[1,8,8,1,1,0,0,0,1],[0,0,8,0,0,0,0,0,1]],
    [[0,0,0,0,0,0,0,0,0],[3,2,8,0,0,0,1,0,0],[0,0,8,0,0,0,0,0,0]],
]
```
<h4>Example</h4>

```PHP
$map = new MapGenerator(10, 12, 4); // Create a map with 10 rows and 12 cells in each row for 4 players.
echo $map->getMap(true); // Display map. True parameters enables compression
```

<?php
/**
 * Class MapGenerator
 *
 * Generates map for SPRD.one game.
 * Cell structure: [player_id, power, max_power, left, up_left, up_right, right, bottom_right, bottom_left]
 *
 * @author Valentyn Simeiko
 * @version 1.0
 */
class MapGenerator
{
    /** @var array Generated map */
    private $map;

    /** @var integer Amount of rows in the generated map */
    private $rows;

    /** @var integer Amount of columns in each row in the generated map */
    private $columns;

    /** @var integer Amount of players */
    private $players;

    /** @var int Cell chance to be a hole */
    private $holeChance = 15;

    /** @var int Cell chance to have a link with another cell */
    private $linkChance = 65;

    /** @var null|array Used to track last random cell row and column. [0] is row, [1] is column */
    private $lastRandomCell;

    /**
     * MapGenerator constructor.
     * Save given parameters; initialize the creation of map.
     *
     * @param $rows integer amount of rows
     * @param $columns integer amount of columns in each row
     * @param $players integer number of players
     */
    public function __construct(int $rows, int $columns, int $players)
    {
        // Set up properties
        $this->rows = $rows;
        $this->columns = $columns;
        $this->players = $players;

        // Build map $this->result
        $this->createMap();
        $this->addHoles();
        $this->validate();
        $this->settlePlayers();
    }

    /**
     * Get the JSON map for output purposes.
     *
     * @param bool $compress call $this->compress() function before returning the map
     * @return string $this->map in JSON format
     */
    public function getMap(bool $compress = false):string {
        if($compress) $this->compress();
        return json_encode($this->map);
    }

    /**
     * Fill up $this->map with cells.
     */
    private function createMap() {
        for($row = 0; $row < $this->rows; $row++) {
            for($column = 0; $column < $this->columns; $column++) {
                $this->map[$row][] = $this->createCell(
                    $column, // First cell in each row

                    $row, // First row

                    ($column+1 !== $this->columns), // Last cell in each row

                    ($row+1 !== $this->rows) // Last row
                );
            }
        }
    }

    /**
     * Create cell array with random links to side cells.
     * Parameters are used to indicate the borders of map in order to prevent linking with non-existing cells.
     *
     * @param bool $left links to left side
     * @param bool $top links to top side
     * @param bool $right links to right side
     * @param bool $bottom links to bottom side
     * @return array Cell array
     */
    private function createCell(bool $left = true, bool $top = true, bool $right = true, bool $bottom = true):array {
        $links = $this->getCellLinks($left, $top, $right, $bottom);

        return [
            0, // Player ID

            0, // Power

            8, // Max power

            (int)$links[0], // Left link

            (int)$links[1], // Up left link

            (int)$links[2], // Up right link

            (int)$links[3], // Right link

            (int)$links[4], // Bottom right link

            (int)$links[5] // Bottom left link
        ];
    }

    /**
     * Creates array with random links to the side cells.
     * Array where [0] key is a link to left cell.
     * Sides are going clockwise, so link with up left cell has a [1] key.
     *
     * @param bool $left links to left side
     * @param bool $top links to top side
     * @param bool $right links to right side
     * @param bool $bottom links to bottom side
     * @return array Links to the side cells
     */
    private function getCellLinks(bool $left = true, bool $top = true, bool $right = true, bool $bottom = true):array {
        $links = array_fill(0, 6, 1); // Create array with sides

        // Remove links that go beyond <canvas> element
        if(!$left) $links[0] = $links[1] = $links[5] = 0;
        if(!$top) $links[1] = $links[2] = 0;
        if(!$bottom) $links[5] = $links[4] = 0;
        if(!$right) $links[3] = $links[4] = $links[2] = 0;

        $allowedSides = $links; // Allowed sides

        // Remove random links
        if($links[0]) $links[0] = $this->chance($this->linkChance); // left
        if($links[1]) $links[1] = $this->chance($this->linkChance); // up left
        if($links[2]) $links[2] = $this->chance($this->linkChance); // up right
        if($links[3]) $links[3] = $this->chance($this->linkChance); // right
        if($links[4]) $links[4] = $this->chance($this->linkChance); // bottom right
        if($links[5]) $links[5] = $this->chance($this->linkChance); // bottom left

        // Each cell must have at least 1 link with another cell
        if(!in_array(true, $links)) {
            if(in_array(true, $allowedSides)) {
                $allowedSides = array_filter($allowedSides); // Remove forbidden sides
                $links[array_rand($allowedSides)] = 1; // Make link with random allowed side
            }
        }

        return $links;
    }

    /**
     * Add random holes to the map.
     * The for loop will run for total amount of map cells ($this->rows * $this->columns).
     * Unlink side cells from the holes.
     */
    private function addHoles() {
        for($i = 0; $i < $this->rows * $this->columns; $i++) {
            if(!$this->chance($this->holeChance)) continue;

            $cell = &$this->getRandomCell(); // Random cell

            if(!$cell) continue; // If returned value is an empty array

            $row = $this->lastRandomCell[0]; // Get the row of last random cell
            $column = $this->lastRandomCell[1]; // Get the column of last random cell
            $cell = [0, 0, 0, 0, 0, 0, 0, 0, 0]; // Clean this cell

            // Remove links to this cell
            if(isset($this->map[$row][$column-1])) $this->map[$row][$column-1][6] = 0; // left
            if(isset($this->map[$row][$column+1])) $this->map[$row][$column+1][3] = 0; // right

            // Each second row have a shift to right
            if($row % 2) {
                if(isset($this->map[$row-1][$column])) $this->map[$row-1][$column][7] = 0; // up left
                if(isset($this->map[$row-1][$column+1])) $this->map[$row-1][$column+1][8] = 0; // up right
                if(isset($this->map[$row+1][$column])) $this->map[$row+1][$column][5] = 0; // bottom left
                if(isset($this->map[$row+1][$column+1])) $this->map[$row+1][$column+1][4] = 0; // bottom right
            } else {
                if(isset($this->map[$row-1][$column-1])) $this->map[$row-1][$column-1][7] = 0; // up left
                if(isset($this->map[$row-1][$column])) $this->map[$row-1][$column][8] = 0; // up right
                if(isset($this->map[$row+1][$column-1])) $this->map[$row+1][$column-1][5] = 0; // bottom left
                if(isset($this->map[$row+1][$column])) $this->map[$row+1][$column][4] = 0; // bottom right
            }
        }
    }

    /**
     * Settle players on the map.
     * Give to each player's cell 2 power to start.
     * Uses $this->getRandomCell() to select random cell.
     *
     * @todo Do not settle players too close
     */
    private function settlePlayers() {
        for($i = 1; $i <= $this->players; $i++) {
            $cell = &$this->getRandomCell();

            if($cell) {
                $cell[0] = $i; // Set the player ID
                $cell[1] = 2; // Set the power to 2
            }
        }
    }

    /**
     * Get random usable cell by reference.
     * If cell is inaccessible or under player's control, try return another cell.
     * Number of attempts to get a random cell is limited to 10.
     * Tracks last random cell with $this->lastRandomCell field.
     *
     * @todo maybe remove return by reference; leave only $this->lastRandomCell
     * @return array empty array on failure; cell array on success
     */
    private function &getRandomCell() {
        try {
            // No more than 10 attempts to get random cell.
            // Prevents forever looping.
            for($i = 0; $i < 10; $i++) {
                $row = random_int(0, $this->rows - 1);
                $column = random_int(0, $this->columns - 1);

                if(!$this->map[$row][$column][2]) continue; // Empty cell (maxPower == 0)
                if($this->map[$row][$column][0]) continue; // Other player

                $this->lastRandomCell = [$row, $column]; // Track last random cell
                return $this->map[$row][$column];
            }
        } catch (Exception $e) {
            $this->lastRandomCell = null;
        }

        $this->lastRandomCell = null;
        $emptyArray = []; // Only variable references should be returned by reference
        return $emptyArray;
    }

    /**
     * Select all cells that surround given cell.
     *
     * @todo shorten this function; foreach() side?
     * @param int $row is a cell row
     * @param int $column is a cell column
     * @param bool $onlyLinked Select only linked cells
     * @return array with near cells where [0] key is row and [1] key is column
     */
    private function getNearCells(int $row, int $column, bool $onlyLinked = true) {
        $nearCells = array_fill(0, 6, false);

        // Left cell
        if(isset($this->map[$row][$column-1])) {
            if(!$onlyLinked && $this->map[$row][$column-1][2]) {
                $nearCells[0] = [$row, $column-1];
            } else if($this->map[$row][$column][3] || $this->map[$row][$column-1][6]) {
                $nearCells[0] = [$row, $column-1];
            }
        }

        // Right cell
        if(isset($this->map[$row][$column+1])) {
            if(!$onlyLinked && $this->map[$row][$column+1][2]) {
                $nearCells[3] = [$row, $column + 1];
            } else if($this->map[$row][$column][6] || $this->map[$row][$column+1][3]) {
                $nearCells[3] = [$row, $column + 1];
            }
        }

        // Each second row have a shift to right
        $odd = $row % 2 ? 0 : 1;
        $even = $row % 2 ? 1 : 0;

        // Up Left cell
        if(isset($this->map[$row-1][$column-$odd])) {
            if(!$onlyLinked && $this->map[$row-1][$column-$odd][2]) {
                $nearCells[1] = [$row - 1, $column-$odd];
            } else if($this->map[$row][$column][4] || $this->map[$row-1][$column-$odd][7]) {
                $nearCells[1] = [$row - 1, $column-$odd];
            }
        }

        // Up Right cell
        if(isset($this->map[$row-1][$column+$even])) {
            if(!$onlyLinked && $this->map[$row-1][$column+$even][2]) {
                $nearCells[2] = [$row - 1, $column + $even];
            } else if($this->map[$row][$column][5] || $this->map[$row-1][$column+$even][8]) {
                $nearCells[2] = [$row - 1, $column + $even];
            }
        }

        // Bottom Right cell
        if(isset($this->map[$row+1][$column+$even])) {
            if(!$onlyLinked && $this->map[$row+1][$column+$even][2]) {
                $nearCells[4] = [$row + 1, $column + $even];
            } else if($this->map[$row][$column][7] || $this->map[$row+1][$column+$even][4]) {
                $nearCells[4] = [$row + 1, $column + $even];
            }
        }

        // Bottom Left cell
        if(isset($this->map[$row+1][$column-$odd])) {
            if(!$onlyLinked && $this->map[$row+1][$column-$odd][2]) {
                $nearCells[5] = [$row + 1, $column-$odd];
            } else if($this->map[$row][$column][8] || $this->map[$row+1][$column-$odd][5]) {
                $nearCells[5] = [$row + 1, $column-$odd];
            }
        }

        return $nearCells;
    }

    /**
     * Get the chance based on $percentage.
     *
     * @param $percentage integer from 0 to 100
     * @return bool True if random number is less or equals to $percentage
     */
    private function chance(int $percentage) {
        try {
            return random_int(1, 100) <= $percentage;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate integrity of map.
     *
     * Procedures:
     * 1) If cell is far and there is no cell near it, delete this cell from the map.
     * 2) If cell is near, but not linked with the "main map part", link it with other cells.
     * 3) Remove everything else that is not linked with "main map part".
     *
     * @return bool false if there is no random cell to start; true if function run successfully
     */
    private function validate():bool {
        if(!$this->getRandomCell()) return false; // If there is no random cell

        // Create $visited array with last random cell position
        $visited = $this->buildMapOfUsableCells($this->lastRandomCell[0], $this->lastRandomCell[1]);

        // Try again if random cell is not surrounded by cells
        if(!$visited) {
            for($i = 0; $i < 5; $i++) {
                if(!$this->getRandomCell()) return false; // If there is no random cell
                $visited = $this->buildMapOfUsableCells($this->lastRandomCell[0], $this->lastRandomCell[1]);
                if($visited) break;
            }
        }

        // Delete far cells and link near cells
        foreach($visited as $row => $rowValue) {
            foreach($rowValue as $column => $columnValue) {
                if($visited[$row][$column]) continue; // We need only NOT visited cells

                $nearCells = array_filter($this->getNearCells($row, $column, false));

                // Remove this cell if there is no near cell
                if(empty($nearCells)) {
                    $this->map[$row][$column] = [0, 0, 0, 0, 0, 0, 0, 0, 0];
                    continue;
                }

                // Link cell with all near cells
                foreach($nearCells as $near) {
                    $this->linkTwoCells($row, $column, $near[0], $near[1]);
                }
            }
        }

        // Update $visited array after changes
        $visited = $this->buildMapOfUsableCells($this->lastRandomCell[0], $this->lastRandomCell[1]);

        // Remove all other cells that aren't accessible
        for($row = 0 ; $row < $this->rows; $row++) {
            for($column = 0; $column < $this->columns; $column++) {
                if(!$visited[$row][$column]) $this->map[$row][$column] = [0, 0, 0, 0, 0, 0, 0, 0, 0];
            }
        }

        return true;
    }

    /**
     * Build map of usable/accessible cells.
     * Returns array similar to $this->map, but instead cell arrays this one will have true/false values.
     *
     * If cell is accessible from Start Cell position, this cell will have true value in the result array.
     * If cell is not reachable from Start position, this cell will have false value in the result array.
     *
     * Basically, this function will return array with all cells that can be reached from $startRow and $startColumn.
     *
     * @param int $startRow Start cell row
     * @param int $startColumn Start cell column
     * @param bool $emptyCells If true, empty cells will be considered as visited
     * @return array|bool false if start cell doesn't have any near cell; otherwise will return an array
     */
    private function buildMapOfUsableCells(int $startRow, int $startColumn, bool $emptyCells = true) {
        // Step 1. Create $visited array
        $visited = [];

        for($row = 0; $row < $this->rows; $row++) {
            for($column = 0; $column < $this->columns; $column++) {
                if($emptyCells) {
                    $visited[$row][] = (bool)!$this->map[$row][$column][2]; // Cells with no max power are visited
                }
            }
        }

        $visited[$startRow][$startColumn] = true; // Mark start cell as a visited

        // Step 2. Get near cells of start cell
        $cellsToVisit = array_filter($this->getNearCells($startRow, $startColumn)); // Only linked near cells

        if(empty($cellsToVisit)) return false;

        $recent = []; // Recently added cells

        // Step 3. Visit all accessible cells
        while(!empty($cellsToVisit)) {
            // Visit each cell from $cellsToVisit array
            foreach($cellsToVisit as $key => $cell) {
                $visited[$cell[0]][$cell[1]] = true; // Mark as visited

                $recent = array_merge($recent, $this->getNearCells($cell[0], $cell[1])); // Get near cells
                $recent = array_filter($recent); // Remove false values (empty cells)
                $recent = array_unique($recent, SORT_REGULAR); // Remove already captured cells (duplicates)

                unset($cellsToVisit[$key]); // clear $cellsToVisit array
            }

            // Step 4. Only NOT visited cells must be in $cellsToVisit array
            foreach($recent as $key => $cell) {
                if($visited[$cell[0]][$cell[1]]) unset($recent[$key]);
            }

            $cellsToVisit = $recent; // Replace empty $cellsToVisit array with new added cells
        }

        return $visited;
    }


    /**
     * Establish connection between cell 1 and cell 2.
     *
     * @param int $row1 cell 1 row
     * @param int $column1 cell 1 column
     * @param int $row2 cell 2 row
     * @param int $column2 cell 2 column
     * @return bool false if cells don't stand near
     */
    private function linkTwoCells(int $row1, int $column1, int $row2, int $column2):bool {
        if($row1 - $row2 < -1 || $row1 - $row2 > 1) return false;
        if($column1 - $column2 < -1 || $column1 - $column2 > 1) return false;

        // Link to left or right
        if($row1 == $row2) {
            if($column1 > $column2) $this->map[$row1][$column1][3] = 1; // link to left
            if($column1 < $column2) $this->map[$row1][$column1][6] = 1; // link to right
        }

        // Link to bottom cells
        if($row1 > $row2) {
            if($row1 % 2) { // each second row has a shift to right
                if($column1 == $column2) $this->map[$row1][$column1][4] = 1; // link to bottom left
                if($column1 < $column2) $this->map[$row1][$column1][5] = 1; // link to bottom right
            } else {
                if($column1 > $column2) $this->map[$row1][$column1][4] = 1; // link to bottom left
                if($column1 == $column2) $this->map[$row1][$column1][5] = 1; // link to bottom right
            }
        }

        // Link to top cells
        if($row1 < $row2) {
            if($row1 % 2) { // each second row has a shift to right
                if($column1 == $column2) $this->map[$row1][$column1][8] = 1; // link to top left
                if($column1 < $column2) $this->map[$row1][$column1][7] = 1; // link to top right
            } else {
                if($column1 > $column2) $this->map[$row1][$column1][8] = 1; // link to top left
                if($column1 == $column2) $this->map[$row1][$column1][7] = 1; // link to top right
            }
        }

        return true;
    }

    /**
     * Remove false values from end of each cell.
     * It reduces the size of map and in the same time increases the load time on the client side.
     * On the client side, the array will be filled up to the normal size.
     *
     * Cell before: [0,0,8,1,1,0,0,0,0]
     * Cell After: [0,0,8,1,1]
     *
     * @todo remove unnecessary cell links
     */
    private function compress() {
        foreach ($this->map as &$row) {
            foreach($row as &$cell) {
                while(0 === end($cell)) {
                    array_pop($cell);
                }
            }
        }
    }
}
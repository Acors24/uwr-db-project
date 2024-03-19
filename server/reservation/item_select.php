<form action="" method="post">
    <h2>Select items to reserve</h2>
    <h3>Station <?= $_SESSION["reservation_data"]["station_index"] + 1 . " out of " . count($_SESSION["reservation_data"]["stations"]) ?></h3>

    <?php

    $i = $_SESSION["reservation_data"]["station_index"];
    $id = $_SESSION["reservation_data"]["stations"][$i];
    $res = pg_query_params($con, "SELECT * FROM station WHERE id = $1", [$id]);

    $row = pg_fetch_all($res);
    if (count($row) !== 1) {
        echo "Something went wrong.";
        die;
    }

    $row = $row[0];

    $name = $row["name"];
    $place = $row["place"];

    $bodies = [];
    $res2 = pg_query($con, "SELECT body FROM connection WHERE $id IN (station1_id, station2_id)");
    while ($row2 = pg_fetch_assoc($res2)) {
        array_push($bodies, $row2["body"]);
    }
    $bodies = join(", ", $bodies);

    echo "<div><b>Name:</b> <span>$name</span></div>";
    echo "<div><b>Place:</b> <span>$place</span></div>";
    echo "<div><b>Bodies:</b> <span>$bodies</span></div>";

    $query = "SELECT *, cost_per_day::numeric AS cost FROM equipment JOIN item ON item.id = equipment.item_id WHERE station_id = $1 ORDER BY item_id";
    $res = pg_query_params($con, $query, [$id]);

    while ($row = pg_fetch_assoc($res)) {
        $item_id = $row["item_id"];
        $name = $row["name"];
        $cost = $row["cost"];
        $avail = $row["available"];
        $max = $row["maximum"];
        echo "<div><label for='input_$name'>$name ($cost zł / day) [available $avail out of $max]: </label>";
        echo "<input type='number' name='$item_id' id='input_$name' value='0' min='0' max='$avail' required></div>";
    }

    ?>

    <div>
        <label for="input_reserved_from">Reservation start</label>
        <input type="date" name="reserved_from" id="input_reserved_from" required>
    </div>

    <div>
        <label for="input_reserved_to">Reservation end</label>
        <input type="date" name="reserved_to" id="input_reserved_to" required>
    </div>

    <button type="submit">Next</button>
</form>
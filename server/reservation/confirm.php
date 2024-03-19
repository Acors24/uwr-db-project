<form action="" method="post">
    <h2>Confirm the data</h2>

    <?php
    
    $tuple = "(-1";
    foreach ($_SESSION["reservation_data"]["stations"] as $key => $id) {
        $tuple = $tuple . ", " . $id;
    }
    $tuple = $tuple . ")";
    $res = pg_query($con, "SELECT * FROM station WHERE id IN $tuple");

    $rows = pg_fetch_all($res);
    $total_for_items_for_interval = 0;
    
    foreach ($rows as $i => $row) {
        $id = $row["id"];
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
        
        echo "<table><thead>";
        echo "<tr><th>Item</th><th>Price</th><th>Availability</th><th>Amount</th></tr>";
        echo "</thead><tbody>";
        
        $from = $_SESSION["reservation_data"]["item_data"][$i]["reserved_from"];
        $to = $_SESSION["reservation_data"]["item_data"][$i]["reserved_to"];
        $from = new DateTime($from);
        $to = new DateTime($to);
        $days = date_diff($from, $to, true)->days + 1;
        $total_for_items = 0;
        $query = "SELECT *, cost_per_day::numeric AS cost FROM equipment JOIN item ON item.id = equipment.item_id WHERE station_id = $1 ORDER BY item_id";
        $res = pg_query_params($con, $query, [$id]);
        while ($row = pg_fetch_assoc($res)) {
            $item_id = $row["item_id"];
            $name = $row["name"];
            $cost = $row["cost"];
            $avail = $row["available"];
            $max = $row["maximum"];
            $amount = $_SESSION["reservation_data"]["item_data"][$i][$item_id];
            $total_for_type = intval($amount) * floatval($cost);
            $total_for_items += $total_for_type;
            $_SESSION["reservation_data"]["item_data"]["prices"][$id][$item_id] = $total_for_type * $days;
            echo "<tr><td>$name</td><td>$cost zł / day</td><td>$avail out of $max</td><td>$amount</td></tr>";
        }
        echo "</tbody></table>";
        $from = $_SESSION["reservation_data"]["item_data"][$i]["reserved_from"];
        $to = $_SESSION["reservation_data"]["item_data"][$i]["reserved_to"];
        echo "<div><b>Reservation start:</b> $from</div>";
        echo "<div><b>Reservation end:</b> $to</div>";
        $from = new DateTime($from);
        $to = new DateTime($to);
        $days = date_diff($from, $to, true)->days + 1;
        $total_for_items_for_interval += $total_for_items * $days;
        echo "<hr>";
    }

    echo "<div><b>Total:</b> $total_for_items_for_interval zł</div>";

    ?>

    <button type="submit">Confirm</button>
</form>
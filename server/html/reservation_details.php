<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation details</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!isset($_SESSION["email"]))
    header("Location: login.php");

function load_details() {
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["Station", "Item", "Amount", "From", "To", "Received", "Returned", "Price"];
    
    $query =
        "SELECT st.id \"st_id\", st.name \"st_name\", st.place, item.name \"item_name\", res_eq.amount, res_eq.reserved_from::date, res_eq.reserved_to::date, res_eq.received_at, res_eq.returned_at, res_eq.price
        FROM reserved_equipment AS res_eq
            JOIN reservation AS res ON res.id = res_eq.reservation_id
            JOIN station AS st ON st.id = res_eq.station_id
            JOIN item ON item.id = res_eq.item_id
        WHERE res.id = $1
        ORDER BY st.name ASC, res_eq.reserved_from ASC";
    $res = pg_query_params($con, $query, [$_GET["res_id"]]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    echo "<table>";
    echo "<thead><tr>";
    foreach ($column_names as $col) {
        echo "<th>$col</th>";
    }
    echo "</thead>";

    echo "<tbody>";
    while ($row = pg_fetch_assoc($res)) {
        $station_id = $row["st_id"];
        $station_name = $row["st_name"];
        $station_place = $row["place"];

        $station_bodies = [];
        $res2 = pg_query($con, "SELECT body FROM connection WHERE $station_id IN (station1_id, station2_id)");
        while ($row2 = pg_fetch_assoc($res2)) {
            array_push($station_bodies, $row2["body"]);
        }
        $station_bodies = join(", ", $station_bodies);

        $station_data = "$station_name, $station_place; $station_bodies";

        $item_name = $row["item_name"];
        $amount = $row["amount"];

        $reserved_from = $row["reserved_from"];
        $reserved_to = $row["reserved_to"];

        $received_at = preg_split("(\.)", $row["received_at"])[0];
        $returned_at = preg_split("(\.)", $row["returned_at"])[0];

        $price = $row["price"];

        $timestamp_from = (new DateTime($reserved_from))->getTimestamp();
        $timestamp_to = (new DateTime($reserved_to))->getTimestamp();
        $class = "";
        if ($timestamp_to < time())
            $class = "expired";
        else if ($timestamp_from < time())
            $class = "ongoing";

        echo "<tr class=\"$class\">";
        echo "<td>$station_data</td>";
        echo "<td>$item_name</td>";
        echo "<td>$amount</td>";
        echo "<td>$reserved_from</td>";
        echo "<td>$reserved_to</td>";
        echo "<td>$received_at</td>";
        echo "<td>$returned_at</td>";
        echo "<td>$price</td>";
        
        echo "</tr>";
    }
    echo "</table>";

    return Result::OK;
}
?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <div class="bread">
            <a href="index.php">My reservations</a>
        </div>

        <h1>Reservation details</h1>

        <?php

        $result = load_details();

        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        ?>
    </main>
</body>

</html>
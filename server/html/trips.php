<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers' trips</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!$_SESSION["is_staff"])
    header("Location: index.php");

if (!isset($_SESSION["email"]))
    header("Location: login.php");

function load_trips() {
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["Name", "Path", "Distance", "Started", "Expected finish", "Finished"];

    $query =
        "SELECT first_name, last_name, path, distance, started_at, expected_at, finished_at, finished, trip.id
        FROM trip
            JOIN \"user\" ON trip.user_id = \"user\".id
        ORDER BY started_at DESC";
    $res = pg_query($con, $query);
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
        $first_name = $row["first_name"];
        $last_name = $row["last_name"];
        $path = $row["path"];
        $path = str_replace(["{", "}"], "", $path);
        $path_array = preg_split("(,)", $path);

        $name_array = [];
        foreach ($path_array as $station_id) {
            $row2 = pg_select($con, "station", ["id" => $station_id]);
            if ($row2 === false)
                return Result::QueryFailed;

            array_push($name_array, $row2[0]["name"]);
        }
        $path = join(", ", $name_array);

        $distance = $row["distance"] / 1000 . " km";
        $started_at = preg_split("(\.)", $row["started_at"])[0];
        $expected_at = preg_split("(\.)", $row["expected_at"])[0];
        $finished_at = preg_split("(\.)", $row["finished_at"])[0];
        $finished = $row["finished"];
        $trip_id = $row["id"];

        $class = "";
        if ($finished === 't')
            $class = "class=\"expired\"";
        echo "<tr $class>";
        echo "<td>$first_name $last_name</td>";
        echo "<td>$path</td>";
        echo "<td>$distance</td>";
        echo "<td>$started_at</td>";
        echo "<td>$expected_at</td>";
        if ($finished === 't') {
            echo "<td>$finished_at</td>";
        } else {
            echo
                "<td>
                    <form action='' method='post'>
                        <input type='hidden' name='trip_id' value='$trip_id'>
                        <button type='submit'>Finish</button>
                    </form>
                </td>";
        }
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";

    return Result::OK;
}

function finish() {
    if ($_SERVER["REQUEST_METHOD"] != "POST")
        return Result::OK;

    require "../credentials.php";
    
    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $query = "UPDATE trip SET finished = 't' WHERE id = $1";
    pg_query_params($con, $query, [$_POST["trip_id"]]);

    return Result::OK;
}

?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <div class="bread">
            <a href="staff_dashboard.php">Staff panel</a>
        </div>

        <h1>Customers' trips</h1>

        <a href="new_trip.php"><button>New trip</button></a>
        <input type="search" name="filter" id="input_filter" placeholder="e.g. Station A">

        <?php

        $result = finish();
        $result2 = load_trips();

        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        if ($result2 !== Result::OK)
            echo "<div class='error'>" . get_message($result2) . "</div>";

        ?>
    </main>
    <script src="js/filter.js"></script>
</body>

</html>
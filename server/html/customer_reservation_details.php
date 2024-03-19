<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer reservation details</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!$_SESSION["is_staff"])
    header("Location: index.php");

if (!isset($_SESSION["email"]))
    header("Location: login.php");

function load_details() {
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["Station", "Item", "Amount", "From", "To", "Received", "Returned", "Price"];
    
    $res_id = $_GET["res_id"];
    $query =
        "SELECT st.id \"st_id\", st.name \"st_name\", st.place, item.id \"item_id\", item.name \"item_name\", res_eq.amount, res_eq.reserved_from::date, res_eq.reserved_to::date, res_eq.received, res_eq.received_at, res_eq.returned, res_eq.returned_at, res_eq.price
        FROM reserved_equipment AS res_eq
            JOIN reservation AS res ON res.id = res_eq.reservation_id
            JOIN station AS st ON st.id = res_eq.station_id
            JOIN item ON item.id = res_eq.item_id
        WHERE res.id = $1
        ORDER BY st.name ASC, res_eq.reserved_from ASC";
    $res = pg_query_params($con, $query, [$res_id]);
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

        $item_id = $row["item_id"];
        $item_name = $row["item_name"];
        $amount = $row["amount"];

        $reserved_from = $row["reserved_from"];
        $reserved_to = $row["reserved_to"];

        $received = $row["received"];
        $received_at = preg_split("(\.)", $row["received_at"])[0];

        $returned = $row["returned"];
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

        if ($received === 't') {
            echo "<td>$received_at</td>";
        } else {
            echo
                "<td>
                    <form action='' method='post'>
                        <div>
                            <input type='hidden' name='receiving'>
                            <input type='hidden' name='res_id' value='$res_id'>
                            <input type='hidden' name='station_id' value='$station_id'>
                            <input type='hidden' name='item_id' value='$item_id'>
                            <input type='submit' value='Confirm'>
                        </div>
                    </form>
                </td>";
        }

        if ($returned === 't') {
            echo "<td>$returned_at</td>";
        } else {
            echo
                "<td>
                    <form action='' method='post'>
                        <div>
                            <input type='hidden' name='returning'>
                            <input type='hidden' name='res_id' value='$res_id'>
                            <input type='hidden' name='station_id' value='$station_id'>
                            <input type='hidden' name='item_id' value='$item_id'>
                            <input type='submit' value='Confirm'>
                        </div>
                    </form>
                </td>";
        }
        
        echo "<td>$price</td>";
        
        echo "</tr>";
    }
    echo "</table>";

    return Result::OK;
}

function process() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST")
        return Result::OK;

    if (!isset($_POST["res_id"], $_POST["station_id"], $_POST["item_id"]))
        return Result::MissingValues;

    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $query = "SELECT at_station FROM \"user\" WHERE id = $1";
    $res = pg_query_params($con, $query, [$_SESSION["user_id"]]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    $staff_station_id = pg_fetch_assoc($res)["at_station"];

    $column = "";
    if (isset($_POST["receiving"]))
        $column = "received";
    elseif (isset($_POST["returning"]))
        $column = "returned";
    else {
        pg_close($con);
        return Result::MissingValues;
    }
    
    $res_id = $_POST["res_id"];
    $station_id = $_POST["station_id"];
    $item_id = $_POST["item_id"];

    pg_query($con, "BEGIN");

    if ($column === "received") {
        $query =
            "UPDATE reserved_equipment
            SET received = 't'
            WHERE reservation_id = $1
                AND station_id = $2
                AND item_id = $3";

        $res = pg_query_params($con, $query, [$res_id, $station_id, $item_id]);
        if ($res === false) {
            pg_query($con, "ROLLBACK");
            pg_close($con);
            return Result::QueryFailed;
        }
    } elseif ($column === "returned") {
        $query =
            "UPDATE reserved_equipment
            SET returned = 't'
            FROM item
            WHERE reservation_id = $1
                AND station_id = $2
                AND item_id = $3
                AND item_id = item.id
                AND item.mobile = 't'";

        $res = pg_query_params($con, $query, [$res_id, $station_id, $item_id]);
        if ($res === false) {
            pg_query($con, "ROLLBACK");
            pg_close($con);
            return Result::QueryFailed;
        }
        $count = pg_affected_rows($res);

        $query =
            "SELECT amount
            FROM reserved_equipment
            WHERE reservation_id = $1
                AND station_id = $2
                AND item_id = $3";
    
        $res = pg_query_params($con, $query, [$res_id, $station_id, $item_id]);
        if ($res === false) {
            pg_query($con, "ROLLBACK");
            pg_close($con);
            return Result::QueryFailed;
        }
    
        $amount = pg_fetch_assoc($res)["amount"];
    
        $query =
            "UPDATE equipment
            SET available = available + $amount
            FROM item
            WHERE equipment.station_id = $1
                AND equipment.item_id = $2
                AND item.id = equipment.item_id
                AND item.mobile = 't'";
    
        $res = pg_query_params($con, $query, [$staff_station_id, $item_id]);
        if ($res === false) {
            pg_query($con, "ROLLBACK");
            pg_close($con);
            return Result::QueryFailed;
        }
        $count += pg_affected_rows($res);

        if ($station_id == $staff_station_id) {
            $query =
                "UPDATE equipment
                SET available = available + $amount
                FROM item
                WHERE equipment.station_id = $1
                    AND equipment.item_id = $2
                    AND item.id = equipment.item_id
                    AND item.mobile = 'f'";
        
            $res = pg_query_params($con, $query, [$station_id, $item_id]);
            if ($res === false) {
                pg_query($con, "ROLLBACK");
                pg_close($con);
                return Result::QueryFailed;
            }
            $count += pg_affected_rows($res);

            $query =
                "UPDATE reserved_equipment
                SET returned = 't'
                FROM item
                WHERE reservation_id = $1
                    AND station_id = $2
                    AND item_id = $3
                    AND item_id = item.id
                    AND item.mobile = 'f'";

            $res = pg_query_params($con, $query, [$res_id, $station_id, $item_id]);
            if ($res === false) {
                pg_query($con, "ROLLBACK");
                pg_close($con);
                return Result::QueryFailed;
            }
            $count += pg_affected_rows($res);
        }
        if ($count === 0) {
            pg_query($con, "ROLLBACK");
            pg_close($con);
            return Result::InsufficientPermissions;
        }
    }

    pg_query($con, "COMMIT");
    pg_close($con);
    return Result::OK;
}

?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <div class="bread">
            <a href="staff_dashboard.php">Staff panel</a>
            <a href="customer_reservations.php">Customers' reservations</a>
        </div>

        <h1>Reservation details</h1>

        <?php

        $result = process();
        $result2 = load_details();

        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        if ($result2 !== Result::OK)
            echo "<div class='error'>" . get_message($result2) . "</div>";

        ?>
    </main>
</body>

</html>
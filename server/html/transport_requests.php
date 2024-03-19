<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport requests</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!$_SESSION["is_staff"])
    header("Location: index.php");

if (!isset($_SESSION["email"]))
    header("Location: login.php");

function load_requests() {
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["Station", "Item", "Amount", "Our stock", "Accept"];

    $query = "SELECT at_station FROM \"user\" WHERE id = $1";
    $res = pg_query_params($con, $query, [$_SESSION["user_id"]]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }
    $row = pg_fetch_assoc($res);
    if ($row === false) {
        pg_close($con);
        return Result::QueryFailed;
    }
    $user_station_id = $row["at_station"];

    $query =
        "SELECT req.id, station.name \"station_name\", item.name \"item_name\", req.amount, eq.available, eq.maximum
        FROM transport_request AS req
            JOIN station ON req.from_station_id = station.id
            JOIN item ON req.item_id = item.id
            CROSS JOIN equipment AS eq
        WHERE item.id = eq.item_id
            AND req.amount <> 0
            AND eq.station_id = $1";
    $res = pg_query_params($con, $query, [$user_station_id]);
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
        $req_id = $row["id"];
        $station_name = $row["station_name"];
        $item_name = $row["item_name"];
        $amount = $row["amount"];
        $available = $row["available"];
        $maximum = $row["maximum"];
        echo "<tr>";
        echo "<td>$station_name</td>";
        echo "<td>$item_name</td>";
        echo "<td>$amount</td>";
        echo "<td>$available out of $maximum</td>";
        echo
            "<td>
                <form action=\"\" method=\"post\">
                    <div>
                        <input type=\"number\" min=\"0\" name=\"amount\">
                        <input type=\"hidden\" name=\"req_id\" value=\"$req_id\">
                        <input type=\"submit\" value=\"Accept\">
                    </div>
                </form>
            </td>";
        echo "</tr>";
    }
    echo "</table>";

    return Result::OK;
}

function verify() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST")
        return Result::OK;

    if (!isset($_POST["amount"]) || !isset($_POST["req_id"]))
        return Result::MissingValues;

    if ($_POST["amount"] < 1)
        return Result::InvalidAmount;

    $amount = $_POST["amount"];
    $req_id = $_POST["req_id"];

    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;
    
    $query = "SELECT item_id FROM transport_request WHERE id = $1";
    $res = pg_query_params($con, $query, [$req_id]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }
    $item_id = pg_fetch_row($res)[0];

    $query = "SELECT at_station FROM \"user\" WHERE id = $1";
    $res = pg_query_params($con, $query, [$_SESSION["user_id"]]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }
    $accepting_station_id = pg_fetch_row($res)[0];

    $query = "SELECT available, maximum FROM equipment WHERE station_id = $1 AND item_id = $2";
    $res = pg_query_params($con, $query, [$accepting_station_id, $item_id]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }
    $row = pg_fetch_assoc($res);
    $available = $row["available"];
    $maximum = $row["maximum"];
    if ($available + $amount > $maximum) {
        pg_close($con);
        return Result::AmountExceeded;
    }

    $values = [
        "request_id" => $req_id,
        "to_station_id" => $accepting_station_id,
        "amount" => $amount
    ];
    $res = pg_insert($con, "transport_accept", $values);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    pg_close($con);
    return Result::OK;
}
?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <div class="bread">
            <a href="staff_dashboard.php">Staff panel</a>
        </div>

        <h1>Transport requests</h1>
        
        <a href="new_request.php"><button>New request</button></a>
        <a href="accepted_requests.php"><button>Accepted requests</button></a>

        <input type="search" name="filter" id="input_filter" placeholder="e.g. Station A">

        <?php

        $result = verify();
        $result2 = load_requests();

        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        if ($result2 !== Result::OK)
            echo "<div class='error'>" . get_message($result2) . "</div>";

        ?>
    </main>
    <script src="js/filter.js"></script>
</body>

</html>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers' reservations</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!$_SESSION["is_staff"])
    header("Location: index.php");

if (!isset($_SESSION["email"]))
    header("Location: login.php");

function process_decision() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST")
        return Result::OK;

    if (!isset($_POST["decision"]) || !isset($_POST["res_id"]))
        return Result::MissingValues;

    $decision = $_POST["decision"];
    $res_id = $_POST["res_id"];

    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $query = "UPDATE reservation SET status = $1 WHERE id = $2";
    $new_status = 1;
    switch ($decision) {
        case 'Accept':
            $new_status = 0;
            break;
        case 'Reject':
            $new_status = 2;
            break;
        
        default:
            # code...
            break;
    }
    $res = pg_query_params($con, $query, [$new_status, $res_id]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    pg_close($con);
    return Result::OK;
}

function load_reservations() {
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["Name", "Email", "From", "To", "Reserved", "Status", "Details"];

    $query =
        "SELECT res.id, first_name, last_name, email, MIN(reserved_from)::date \"from\", MAX(reserved_to)::date \"to\", reserved_at, status
        FROM reservation AS res
            JOIN \"user\" ON res.user_id = \"user\".id
            JOIN reserved_equipment ON reservation_id = res.id
        GROUP BY res.id, first_name, last_name, email
        ORDER BY MIN(reserved_from) DESC";
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
        $res_id = $row["id"];
        $name = $row["first_name"] . " " . $row["last_name"];
        $email = $row["email"];
        $reserved_from = $row["from"];
        $reserved_to = $row["to"];
        $reserved_at = $row["reserved_at"];
        $status = $row["status"];

        $timestamp_from = (new DateTime($reserved_from))->getTimestamp();
        $timestamp_to = (new DateTime($reserved_to))->getTimestamp();
        $class = "";
        $status_msg = "Confirmed";
        $pending = false;
        switch ($status) {
            case "1":
                $class = "pending";
                $status_msg = "Pending";
                $pending = true;
                break;
            
            case "2":
                $class = "rejected";
                $status_msg = "Rejected";
                break;
            
            default:
                break;
        }
        if ($timestamp_to < time())
            $class = "expired";
        else if ($timestamp_from < time() && $status == "0")
            $class = "ongoing";
        else if ($status == 2)
            $class = "rejected";
        echo "<tr class=\"$class\">";
        echo "<td>$name</td>";
        echo "<td>$email</td>";
        echo "<td>$reserved_from</td>";
        echo "<td>$reserved_to</td>";
        $reserved_at = preg_split("(\.)", $reserved_at)[0];
        echo "<td>$reserved_at</td>";
        if ($pending)
            echo
                "<td>
                    <form action='' method='post'>
                        <div>
                            <input type='hidden' name='res_id' value='$res_id'>
                            <input type='submit' name='decision' value='Accept'>
                            <input type='submit' name='decision' value='Reject'>
                        </div>
                    </form>
                </td>";
        else
            echo "<td>$status_msg</td>";
        echo
            "<td>
                <a href='customer_reservation_details.php?res_id=$res_id'>
                    <input type='button' value='Details'>
                </a>
            </td>";
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
            <a href="staff_dashboard.php">Staff panel</a>
        </div>

        <h1>Customers' reservations</h1>
        <input type="search" name="filter" id="input_filter" placeholder="e.g. Rejected">

        <?php

        $result = process_decision();
        $result2 = load_reservations();

        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";
        
        if ($result2 !== Result::OK)
            echo "<div class='error'>" . get_message($result2) . "</div>";

        ?>
    </main>
    <script src="js/filter.js"></script>
</body>

</html>
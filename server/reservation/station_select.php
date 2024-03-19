<form action="" method="post">
    <div>
        <h2>Select stations</h2>
        <input type="search" name="filter" id="input_filter" placeholder="e.g. Station A">
    </div>

    <select name="stations[]" id="select_station" multiple required>
        <?php

        foreach (STATIONS as $i => $row) {
            $id = $row["id"];
            $name = $row["name"];
            $place = $row["place"];

            $bodies = [];
            $res2 = pg_query($con, "SELECT body FROM connection WHERE $id IN (station1_id, station2_id)");
            while ($row2 = pg_fetch_assoc($res2)) {
                array_push($bodies, $row2["body"]);
            }
            $bodies = join(", ", $bodies);

            echo "<option value='$id'>$name, $place; $bodies</option>";
        }

        ?>
    </select>
    <div class="hint">Hold <kbd>Ctrl</kbd>, to toggle selection.</div>
    <button type="submit">Next</button>
</form>

<script src="js/filter.js"></script>

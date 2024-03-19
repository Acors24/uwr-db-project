<nav>
    <ul>
        <a href="index.php"><li>My reservations</li></a>
        <?php if ($_SESSION["is_staff"]) echo "<a href=\"staff_dashboard.php\"><li>Staff panel</li></a>" ?>
    </ul>

    <ul>
        <li><span><?= $_SESSION["first_name"] . " " . $_SESSION["last_name"] ?></span></li>
        <a href="logout.php"><li>Log out</li></a>
    </ul>
</nav>
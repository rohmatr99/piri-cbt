<?php

date_default_timezone_set("Asia/Jakarta");

require_once "config/database.php";

echo "Waktu PHP: " . date("Y-m-d H:i:s") . "<br>";
echo "Timezone PHP: " . date_default_timezone_get() . "<br><br>";

$result = $conn->query("
    SELECT
        NOW() AS waktu_mysql,
        @@session.time_zone AS timezone_session,
        @@global.time_zone AS timezone_global,
        @@system_time_zone AS timezone_system
");

$data = $result->fetch_assoc();

echo "Waktu MySQL: " . $data["waktu_mysql"] . "<br>";
echo "Timezone MySQL Session: " . $data["timezone_session"] . "<br>";
echo "Timezone MySQL Global: " . $data["timezone_global"] . "<br>";
echo "Timezone Sistem MySQL: " . $data["timezone_system"];
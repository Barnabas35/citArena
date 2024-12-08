<?php

// MySQLi vars
$servername = "mtuarena.chetje7buaff.eu-west-1.rds.amazonaws.com";
$username = "admin";
$password = "mtuArenaRootUserPassword";
$dbname = "mtuarena_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

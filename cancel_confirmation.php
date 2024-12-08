<?php
global $conn;
session_start();
require_once 'database_connection.php';

// Getting session token
$session_token = $_SESSION['session_token'];

// Building the SQL query
$sql_query = "SELECT * FROM mtuarena_db.user WHERE session_token = '$session_token'";

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php');
    exit();
}

// Need this comment or else the IDE complains about $conn not being defined
/** @var mysqli $conn */
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

// Check if the session token is valid
if ($result->num_rows === 0) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$user_type = null;
$logged_in_user_id = null;

// Check if session token is set, and if so, get user type
if (isset($_SESSION['username']) && isset($_SESSION['session_token'])) {
    $session_token = $_SESSION['session_token'];

    $sql_query = "SELECT user_type, user_id FROM mtuarena_db.user WHERE session_token = ?";
    /** @var mysqli $conn */
    $stmt = $conn->prepare($sql_query);
    $stmt->bind_param("s", $session_token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_type = $row['user_type'];
        $logged_in_user_id = $row['user_id'];
    } else {
        header('Location: login.php');;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Confirmation</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            text-align: center;
            padding: 50px;
        }
        .cancel-button, .go-back-button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .cancel-button {
            background-color: red;
            color: white;
        }
        .go-back-button {
            background-color: gray;
            color: white;
        }
    </style>
</head>
<header>
    <nav>
        <ul>
            <?php if (isset($_SESSION['username']) && isset($_SESSION['session_token'])): ?>
                <li><a href="user_profile.php">My Profile</a></li>
            <?php endif; ?>

            <li><a href="index.php">Home</a></li>

            <?php if (isset($_SESSION['username']) && isset($_SESSION['session_token'])): ?>
                <li><a href="search_clubs.php">Search Clubs</a></li>
                <li><a href="search_bookings.php">Search Bookings</a></li>
                <li><a href="app_booking.php">Appointment Booking</a></li>
                <li><a href="fitness_log.php">Fitness Log</a></li>
            <?php endif; ?>

            <?php if (isset($user_type) && $user_type === 'Admin'): ?>
                <li><a href="admin_tools.php">Admin Tools</a></li>
            <?php endif; ?>


            <?php if ($user_type === 'Staff'): ?>
                <li><a href="facility_booking.php">Book Club Session</a></li>
            <?php endif; ?>

            <?php if (!isset($_SESSION['username'])): ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            <?php else: ?>
                <li><a href="logout.php">Logout</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<body>

<div class="container">
    <?php if ($successful_cancellation):?>
    <p>
        Cancellation was Successful!
    </p>

    <?php else: ?>
    <p>
        Cancellation was Unsuccessful.
    </p>

    <?php endif; ?>

    <!-- Go Back button -->
    <a href="search_bookings.php" class="go-back-button">Go Back</a>
</div>

</body>
</html>

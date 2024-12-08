<?php
require_once 'database_connection.php';

// Start session to check if user is logged in
session_start();

$is_trainer = false;

// If session token is set then getting user type
if (isset($_SESSION['username']) && isset($_SESSION['session_token'])) {
    $session_token = $_SESSION['session_token'];

    $sql_query = "SELECT user_type, user_id FROM mtuarena_db.user WHERE session_token = '$session_token'";

    // Need this comment or else the IDE complains about $conn not being defined
    /** @var mysqli $conn */
    $result = $conn->query($sql_query);
    $row = $result->fetch_assoc();

    $user_type = $row['user_type'];
    $user_id = $row['user_id'];

    // Checking if user is in trainers list
    $sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$user_id'";
    $result = $conn->query($sql_query);
    $row = $result->fetch_assoc();

    if ($row) {
        $is_trainer = true;
    }

    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to MTU Arena</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- Navbar -->
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


            <?php if ($is_trainer): ?>
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

    <!-- Main Content -->
    <main class="content-container">
        <h1>Welcome to the MTU Arena Training Booking</h1>
        <p>Use the navigation bar to explore the available features.</p>

        <?php if (isset($_SESSION['username']) && isset($_SESSION['session_token'])): ?>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! You can book training sessions and appointments.</p>
        <?php else: ?>
            <p>Please <a href="login.php">log in</a> or <a href="register.php">register</a> to access booking features.</p>
        <?php endif; ?>
    </main>
</body>
</html>

<?php
require_once 'database_connection.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php');
    exit();
}

// Getting the user type - Redirecting if the user is not an admin ----------------------------
$session_token = $_SESSION['session_token'];

$sql_query = "SELECT * FROM mtuarena_db.user WHERE session_token = '$session_token'";

/** @var mysqli $conn */
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

$user_type = $row['user_type'];

// Checking if the user is an admin
if ($user_type !== 'Admin') {
    header('Location: index.php');
    exit();
}

// Searching for users & reset user failed login attempts -------------------------------------
$search = '';

// If the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action_type']) && isset($_POST['user_id'])) {
        $action_type = $_POST['action_type'];
        $user_id = $_POST['user_id'];

        if ($action_type === 'reset') {
            $sql_query = "UPDATE mtuarena_db.user SET failed_login = 0 WHERE user_id = $user_id";
            $conn->query($sql_query);
        }

        if ($action_type === 'delete') {
            $sql_query = "DELETE FROM mtuarena_db.user WHERE user_id = $user_id";
            $conn->query($sql_query);
        }
    }

    if (isset($_POST['search'])){
        $search = $_POST['search'];
        $sql_query = "SELECT * FROM mtuarena_db.user WHERE username LIKE '%$search%'";
        $result = $conn->query($sql_query);
    }
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


<main class="content-container">
    <h1>Welcome to Admin Tools</h1>
    <p>You can remove a user account or reset their failed login attempts.</p>

    <!-- Search bar -->
    <form action="admin_tools.php" method="post">
        <label for="search">Search for a user:</label>
        <input type="text" id="search" name="search" value="<?php echo(isset($_POST['search']) ? $search : '')?>" required>
        <button type="submit">Search</button>
    </form>

    <!-- Displaying search results -->
    <h3>Search Results:</h3>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {
            echo '<div style="display: flex; justify-content: space-between">';
            echo ('<p>' . $row['username'] . '</p>');
            echo ('<form action="admin_tools.php" method="post">
                        <button type="submit">Delete User</button>
                        <input type="hidden" name="action_type" id="action_type" value="delete">
                        <input type="hidden" name="user_id" id="user_id" value="' . $row['user_id'] . '">
                        <input type="hidden" name="search" id="search" value="' . $search . '">
                   </form>');
            echo ('<p>Attempts: </p>');
            echo ('<p id="attempts">' . $row['failed_login'] . '</p>');
            echo ('<form action="admin_tools.php" method="post">
                        <button type="submit">Reset Failed Attempts</button>
                        <input type="hidden" name="action_type" id="action_type" value="reset">
                        <input type="hidden" name="user_id" id="user_id" value="' . $row['user_id'] . '">
                        <input type="hidden" name="search" id="search" value="' . $search . '">
                   </form>');
            echo '</div>';
        }

    } else {
        echo '<p>Search a different username.</p>';
    }
    ?>

</main>

</body>
</html>

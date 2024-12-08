<?php
require_once 'database_connection.php';

session_start();

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php');
    exit();
}

// Getting session token
$session_token = $_SESSION['session_token'];

// Building the SQL query
$sql_query = "SELECT * FROM mtuarena_db.user WHERE session_token = '$session_token'";

// Need this comment or else the IDE complains about $conn not being defined
/** @var mysqli $conn */
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

// Check if the session token is valid
if ($result->num_rows === 0) {
    header('Location: login.php');
    exit();
}

// Getting the user's data
$user_id = $row['user_id'];
$username = $row['username'];
$full_name = $row['full_name'];
$email = $row['email'];
$phone_number = $row['phone_number'];
$address = $row['address'];
$height = $row['height'];
$user_type = $row['user_type'];
$membership = $row['membership'];

// Checking if user is in trainers list
$sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$user_id'";
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

$is_trainer = false;

if ($row) {
    $is_trainer = true;
}

// Checking if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    if ($action_type === 'update') {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone_number = $_POST['phone_number'];
        $address = $_POST['address'];
        $height = $_POST['height'];

        // Building the SQL query
        $sql_query = "UPDATE mtuarena_db.user SET full_name = '$full_name', email = '$email', phone_number = '$phone_number', address = '$address', height = '$height' WHERE user_id = '$user_id'";

        // Executing the query
        $conn->query($sql_query);

        // Redirecting to the user profile page
        header('Location: user_profile.php');
        exit();
    }

    if ($action_type === 'delete') {
        // Building the SQL query
        $sql_query = "DELETE FROM mtuarena_db.user WHERE user_id = '$user_id'";

        // Executing the query
        $conn->query($sql_query);

        // Redirecting to the login page
        header('Location: login.php');
        exit();
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

    <main class="content-container">
        <h1 style="text-align: center">Welcome <?php echo($username)?></h1>
        <img style="margin-left: auto; margin-right: auto; display: block" alt="User Profile Icon" src="img/user_icon.png" width="20%">

        <h2>Your Profile:</h2>

        <!-- Only showing the normal text profile if no form has been submitted OR if the action type was cancel -->
        <?php if ((isset($_POST['action_type']) && $_POST['action_type'] === 'cancel') || !($_SERVER['REQUEST_METHOD'] === 'POST')): ?>

        <p>Full Name: <?php echo($full_name)?></p>
        <p>Email: <?php echo($email)?></p>
        <p>Phone Number: <?php echo($phone_number)?></p>
        <p>Address: <?php echo($address)?></p>
        <p>Height: <?php echo($height)?>cm</p>
        <p>User Type: <?php echo($user_type)?></p>
        <p>Membership: <?php echo($membership)?></p>

        <br>

        <div style="display: flex; justify-content: space-between">
            <div style="margin-right: auto">
                <form action="user_profile.php" method="post">
                    <button type="submit">Edit Profile</button>
                    <input type="hidden" name="action_type" id="action_type" value="edit">
                </form>
            </div>

            <div style="margin-left: auto">
                <form action="user_profile.php" method="post" onsubmit="return confirm('Do you really want delete your account?');">
                    <button type="submit">Delete Account</button>
                    <input type="hidden" name="action_type" id="action_type" value="delete">
                </form>
            </div>
        </div>

        <?php endif; ?>


        <!-- Only showing the update and cancel button if the action type was update -->
        <?php if (isset($_POST['action_type']) && $_POST['action_type'] === 'edit'): ?>

        <form action="user_profile.php" method="post">
            <div style="display: flex; flex-direction: column; gap: 13px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="full_name" style="width: 120px;">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo($full_name) ?>" required>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="email" style="width: 120px;">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo($email) ?>" required>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="phone_number" style="width: 120px;">Phone Number:</label>
                    <input type="tel" id="phone_number" name="phone_number" value="<?php echo($phone_number) ?>" required>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="address" style="width: 120px;">Address:</label>
                    <input type="text" id="address" name="address" value="<?php echo($address) ?>" required>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="height" style="width: 120px;">Height (cm):</label>
                    <input type="number" id="height" name="height" value="<?php echo($height) ?>" required>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label>User Type: <?php echo($user_type)?></label>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label>Membership: <?php echo($membership)?></label>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="submit">Update Profile</button>
                </div>
            </div>

            <input type="hidden" name="action_type" id="action_type" value="update">
        </form>

        <form action="user_profile.php" method="post">
            <button type="submit">Cancel Update</button>
            <input type="hidden" name="action_type" id="action_type" value="cancel">
        </form>

        <?php endif; ?>

    </main>
</body>
</html>

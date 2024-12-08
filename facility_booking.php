<?php
require_once 'database_connection.php';

// Start session to check if user is logged in
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
        $stmt->close();
    } else {
        $stmt->close();
        header('Location: login.php');;
    }
} else {
    header('Location: login.php');
}

// Checking if user is in trainers list
$sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$logged_in_user_id'";
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

$is_trainer = false;

if ($row) {
    $is_trainer = true;
}

if ($is_trainer === false) {
    die("Error: You must be a trainer member to access this page.");
}

$user_id = $logged_in_user_id;


// Fetch trainer_id from the trainer table using the user_id
$trainer_query = "SELECT trainer_id FROM trainer WHERE user_id = ?";
$stmt = $conn->prepare($trainer_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trainer_result = $stmt->get_result();
$trainer_data = $trainer_result->fetch_assoc();

if (!$trainer_data) {
    die("Error: Trainer ID not found.");
}

$trainer_id = $trainer_data['trainer_id'];
$stmt->close();

// Fetch club_id based on trainer_id
$club_query = "SELECT club_id FROM club WHERE trainer_id = ?";
$stmt = $conn->prepare($club_query);
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$club_result = $stmt->get_result();
$club_data = $club_result->fetch_assoc();

if (!$club_data) {
    die("Error: Club ID not found.");
}

$club_id = $club_data['club_id'];
$stmt->close();

// Fetch available facilities for the dropdown
$facility_query = "SELECT facility_id, building, room, capacity FROM facility";
$facility_result = $conn->query($facility_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['cancel_session'])) {
    $facility_id = $_POST['facility_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $club_id = $_POST['club_id'];

    // Check if the facility is already booked at the specified date and time
    $booking_check_query = "SELECT * FROM club_training_session WHERE facility_id = ? AND date = ? AND start_time = ?";
    $stmt = $conn->prepare($booking_check_query);
    $stmt->bind_param("iss", $facility_id, $date, $start_time);
    $stmt->execute();
    $booking_check_result = $stmt->get_result();

    if ($booking_check_result->num_rows > 0) {
        $error_message = "This facility is already booked for the selected date and time.";
    } else {
        // Insert new booking if the facility is available
        $insert_booking_query = "INSERT INTO club_training_session (club_id, facility_id, date, start_time) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_booking_query);
        $stmt->bind_param("iiss", $club_id, $facility_id, $date, $start_time);

        if ($stmt->execute()) {
            $success_message = "Facility booked successfully!";
        } else {
            $error_message = "Error booking facility: " . $stmt->error;
        }
    }

    // Close the statement
    $stmt->close();
}

// Handle session cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_session'])) {
    $club_training_session_id = $_POST['cancel_session'];

    // Delete the session
    $delete_query = "DELETE FROM club_training_session WHERE club_training_session_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $club_training_session_id);
    $stmt->execute();

    // Delete bookings for the session
    $get_booking_query = "SELECT training_session_booking_id FROM training_session_booking WHERE club_training_session_id = ?";
    $stmt2 = $conn->prepare($get_booking_query);
    $stmt2->bind_param("i", $club_training_session_id);
    $stmt2->execute();

    $booking_result = $stmt2->get_result();

    while ($booking = $booking_result->fetch_assoc()) {
        $delete_booking_query = "DELETE FROM training_session_booking WHERE training_session_booking_id = ?";
        $stmt3 = $conn->prepare($delete_booking_query);
        $stmt3->bind_param("i", $booking['club_training_session_booking_id']);
        $stmt3->execute();
        $stmt3->close();
    }

    if ($stmt->affected_rows > 0 ) {
        $success_message = "Session cancelled successfully!";
    } else {
        $error_message = "Error cancelling session.";
    }

    // Close the statement
    $stmt->close();
    $stmt2->close();
}


// Fetching all training sessions where the clubs' trainer is the logged-in user
$sql_query = "SELECT * FROM club_training_session WHERE club_training_session.club_id IN (SELECT club.club_id FROM club WHERE club.trainer_id = '$trainer_id')";
$result = $conn->query($sql_query);

// getting all clubs belonging to the trainer
$club_query = "SELECT * FROM club WHERE trainer_id = '$trainer_id'";
$club_result2 = $conn->query($club_query);



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Booking</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
        }
    </style>
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

 <div class="content-container">
        <h1 class="page-title">Book a Facility</h1>

        <form action="facility_booking.php" method="POST" class="appointment-form">

            <label for="club_id">Club:</label>
            <select id="club_id" name="club_id" required style="width: 350px">
                <?php while ($club = $club_result2->fetch_assoc()): ?>
                    <option value="<?= $club['club_id'] ?>">
                        <?= $club['name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="facility_id">Facility:</label>
            <select id="facility_id" name="facility_id" required style="width: 350px">
                <?php while ($facility = $facility_result->fetch_assoc()): ?>
                    <option value="<?= $facility['facility_id'] ?>">
                        <?= $facility['building'] ?>, Room <?= $facility['room'] ?> (Capacity: <?= $facility['capacity'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="date">Date:</label>
            <input type="date" id="date" name="date" required style="width: 150px">

            <label for="start_time">Start Time:</label>
            <input type="time" id="start_time" name="start_time" required style="width: 150px">

            <button type="submit" class="submit-button">Book Facility</button>
        </form>
    </div>

    <?php
        if (isset($success_message)) {
            echo "<div class='alert' style='max-width: 800px; margin-left: 35%; margin-right: 35%'>";
            echo "<p class='alert-success'>$success_message</p>";
            echo "</div>";
        }
        if (isset($error_message)) {
            echo "<div class='alert' style='max-width: 800px; margin-left: 35%; margin-right: 35%'>";
            echo "<p class='alert-error'>$error_message</p>";
            echo "</div>";
        }
    ?>

    <div class="content-container" style="max-width: 700px">
        <h1 class="page-title">Your Booked Facilities</h1>

        <?php
            // Display club cards
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {

                    // Getting club from club_id
                    $club_id = $row['club_id'];
                    $club_query = "SELECT * FROM club WHERE club_id = '$club_id'";
                    $club_result = $conn->query($club_query);
                    $club_data = $club_result->fetch_assoc();

                    // Getting facility from facility_id
                    $facility_id = $row['facility_id'];
                    $facility_query = "SELECT * FROM facility WHERE facility_id = '$facility_id'";
                    $facility_result = $conn->query($facility_query);
                    $facility_data = $facility_result->fetch_assoc();


                    echo "<div class='club-card'>";
                    echo "<img src='" . htmlspecialchars($club_data['profile_img_url']) . "' alt='" . htmlspecialchars($club_data['name']) . " logo' class='club-logo' style='width: 150px; height: 150px'>";
                    echo "<div class='club-details'>";
                    echo "<h2>" . htmlspecialchars($club_data['name']) . "</h2>";
                    echo ("<p><strong>Location:</strong> " . htmlspecialchars($facility_data['building']) . "</p>");
                    echo ("<p><strong>Date:</strong> " . htmlspecialchars($row['date']) . "</p>");
                    echo ("<p><strong>Start Time:</strong> " . htmlspecialchars($row['start_time']) . ":00</p>");
                    echo "</div>";
                    echo "<form action='facility_booking.php' method='POST'><button type='submit' name='cancel_session' id='cancel_session' class='submit-button' style='background-color: #dc3545; margin-right: 20px; transform: translateY(-35px)' value='" . htmlspecialchars($row['club_training_session_id']) . "'>Cancel Session</button></form>";
                    echo "</div>";
                }
            } else {
                echo "<div class='club-card'><h3>No clubs found.</h3></div>";
            }
        ?>
    </div>
</body>
</html>

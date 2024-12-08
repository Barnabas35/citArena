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

$is_trainer = false;

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

        // Checking if user is in trainers list
        $sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$logged_in_user_id'";
        $result = $conn->query($sql_query);
        $row = $result->fetch_assoc();

        if ($row) {
            $is_trainer = true;
        }

    } else {
        header('Location: login.php');;
    }
}

// Get club_id from URL parameter
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

// Check for valid club_id
if ($club_id <= 0) {
    die("Invalid Club ID.");
}

// Fetch club details along with trainer's information, including user_id of the trainer
$sql = "
    SELECT 
        club.name AS club_name, 
        club.expected_cal_burnt, 
        club.profile_img_url, 
        club.club_type, 
        trainer.office_room_number, 
        user.full_name AS trainer_name, 
        user.user_id AS assigned_user_id 
    FROM club 
    LEFT JOIN trainer ON club.trainer_id = trainer.trainer_id
    LEFT JOIN user ON trainer.user_id = user.user_id
    WHERE club.club_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $club = $result->fetch_assoc();
} else {
    echo "<h1>Club not found.</h1>";
    exit;
}

// Fetch schedule of training sessions for the club
$schedule_sql = "
    SELECT 
        club_training_session.date, 
        club_training_session.start_time, 
        facility.building, 
        facility.room, 
        facility.capacity AS max_attendance,
        (SELECT COUNT(*) FROM training_session_booking WHERE training_session_booking.club_training_session_id = club_training_session.club_training_session_id) AS currently_booked
    FROM club_training_session 
    LEFT JOIN facility ON club_training_session.facility_id = facility.facility_id 
    WHERE club_training_session.club_id = ? 
    ORDER BY club_training_session.date ASC
";

$schedule_stmt = $conn->prepare($schedule_sql);
$schedule_stmt->bind_param("i", $club_id);
$schedule_stmt->execute();
$schedule_result = $schedule_stmt->get_result();
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($club['club_name']); ?> Profile</title>
        <link rel="stylesheet" href="style.css">
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container {
                width: 80%;
                margin: 20px auto;
                padding: 20px;
                background-color: #fff;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                border-radius: 8px;
            }
            h1 {
                color: #333;
                text-align: center;
            }
            .club-details img {
                width: 100px;
                height: 100px;
                border-radius: 10px;
                object-fit: cover;
            }
            .club-details {
                margin-top: 20px;
                text-align: center;
            }
            .club-details p {
                font-size: 18px;
                color: #555;
                margin: 5px 0;
            }
            .book-button {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #007BFF;
                color: white;
                font-size: 18px;
                font-weight: bold;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s ease;
            }
            .book-button:hover {
                background-color: #0056b3;
            }
            .button-spacing {
                margin-right: 10px; /* Adds space between buttons */
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


    <div class="container">
        <h1><?php echo htmlspecialchars($club['club_name']); ?></h1>

        <div class="club-details">
            <img src="<?php echo htmlspecialchars($club['profile_img_url']); ?>" alt="<?php echo htmlspecialchars($club['club_name']); ?> logo">
            <p><strong>Club Type:</strong> <?php echo htmlspecialchars($club['club_type']); ?></p>
            <p><strong>Trainer:</strong> <?php echo htmlspecialchars($club['trainer_name']); ?></p>
            <p><strong>Trainer Office:</strong> Room <?php echo htmlspecialchars($club['office_room_number']); ?></p>
            <p><strong>Expected Calories Burned:</strong> <?php echo htmlspecialchars($club['expected_cal_burnt']); ?></p>

            <!-- Display Schedule -->
            <p><strong>Schedule:</strong></p>
            <?php
            if ($schedule_result && $schedule_result->num_rows > 0) {
                while ($session = $schedule_result->fetch_assoc()) {
                    echo "<p>Date: " . htmlspecialchars($session['date']) .
                        " | Time: " . htmlspecialchars($session['start_time']) . ":00" .
                        " | Location: " . htmlspecialchars($session['building']) . ", " . htmlspecialchars($session['room']) .
                        " | Max Attendance: " . htmlspecialchars($session['max_attendance']) .
                        " | Currently Booked: " . htmlspecialchars($session['currently_booked']) . "</p>";
                }
            } else {
                echo "<p>No sessions scheduled.</p>";
            }

            // "Book Training Session" button available for all users
            echo "<a href='training_session_booking.php?club_id=$club_id>' class='book-button button-spacing'>Book Training Session</a>";

            // "Book Training Facility" only for the trainer assigned to this club based on user_id
            if ($club['assigned_user_id'] == $logged_in_user_id) {
                echo "<a href='facility_booking.php' class='book-button'>Book Training Facility</a>";
            }
            ?>
        </div>
    </div>

    </body>
    </html>

<?php
// Close prepared statements and database connection
$stmt->close();
$schedule_stmt->close();
$conn->close();
?>
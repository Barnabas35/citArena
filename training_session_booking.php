<?php
global $conn;
session_start();
require_once 'database_connection.php';

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php');
    exit();
}

// Fetch the session token
$session_token = $_SESSION['session_token'];

// Fetch user details
$sql_query = "SELECT * FROM mtuarena_db.user WHERE session_token = ?";
$stmt = $conn->prepare($sql_query);
$stmt->bind_param("s", $session_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: login.php');
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$user_type = $user['user_type'];

$is_trainer = false;

// Checking if user is in trainers list
$sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$user_id'";
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

if ($row) {
    $is_trainer = true;
}

// Process booking requests
$message = ""; // Store success message
$message_type = ""; // Success or error type for styling
$error_message = ""; // Store error message

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['club_training_session_id'])) {
    $club_training_session_id = intval($_POST['club_training_session_id']);

    if ($club_training_session_id > 0) {
        // Check if the session is already booked
        $check_query = "SELECT COUNT(*) AS count FROM training_session_booking WHERE club_training_session_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $club_training_session_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();

        if ($check_row['count'] > 0) {
            $message = "You have already booked this training session.";
            $message_type = "error";
        } else {
            // Book the session
            $insert_query = "INSERT INTO training_session_booking (club_training_session_id, user_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ii", $club_training_session_id, $user_id);

            if ($insert_stmt->execute()) {
                $message = "Booking Made Successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to Make the Booking. Please try again.";
                $message_type = "error";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $message = "Invalid Training Session ID.";
        $message_type = "error";
    }
}

// Fetch available training sessions
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
$sql = "
    SELECT
        cts.club_training_session_id,
        c.name AS club_name,
        f.building,
        f.room,
        cts.date,
        cts.start_time
    FROM club_training_session cts
    JOIN club c ON cts.club_id = c.club_id
    JOIN facility f ON cts.facility_id = f.facility_id
    LEFT JOIN training_session_booking tsb 
        ON cts.club_training_session_id = tsb.club_training_session_id AND tsb.user_id = ?
    WHERE tsb.club_training_session_id IS NULL AND cts.club_id = ?
    ORDER BY cts.date ASC, cts.start_time ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Training Sessions</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
        }

        .button-container button {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            color: #fff;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            max-width: 100px;
        }

        .button-container button:hover {
            background-color: #0056b3;
        }

        .content-container {
            margin: 0 auto;
            max-width: 80%;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
        }

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
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

<main class="content-container">
    <h1>Available Training Sessions</h1>

    <!-- Display Success or Error Message -->
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <form method="POST">
            <table>
                <thead>
                <tr>
                    <th>Club Name</th>
                    <th>Building</th>
                    <th>Room</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['club_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['building']); ?></td>
                        <td><?php echo htmlspecialchars($row['room']); ?></td>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo htmlspecialchars($row['start_time']); ?></td>
                        <td class="button-container">
                            <button type="submit" name="club_training_session_id" value="<?php echo htmlspecialchars($row['club_training_session_id']); ?>">Book</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </form>
    <?php else: ?>
        <p>No available training sessions for this club.</p>
    <?php endif; ?>
</main>
</body>
</html>

<?php
$conn->close();
?>

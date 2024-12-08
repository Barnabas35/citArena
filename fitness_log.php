<?php
global $conn;
session_start();
require_once 'database_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php');
    exit();
}

// Get session token
$session_token = $_SESSION['session_token'];

// Validate session token and retrieve user details
$sql_query = "SELECT user_type, user_id FROM mtuarena_db.user WHERE session_token = ?";
$stmt = $conn->prepare($sql_query);
$stmt->bind_param("s", $session_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: login.php');
    exit();
}

$row = $result->fetch_assoc();
$user_type = $row['user_type'];
$user_id = $row['user_id']; // Ensure $user_id is assigned for fitness log query

// Checking if user is in trainers list
$sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$user_id'";
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

$is_trainer = false;

if ($row) {
    $is_trainer = true;
}

// Fetch fitness logs from the fitness_log table for the current user
$fitness_logs = [];
$sql_query = "SELECT weight, bmr, unix_timestamp FROM fitness_log WHERE user_id = ? ORDER BY unix_timestamp DESC";
$stmt = $conn->prepare($sql_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch data and convert UNIX timestamp to a readable date
while ($log = $result->fetch_assoc()) {
    $log['date'] = date("Y-m-d H:i:s", $log['unix_timestamp']); // Convert timestamp to date
    $fitness_logs[] = $log;
}

// BMR calculation function
function calculate_bmr($gender, $weight, $height, $age) {
    if ($gender == "male") {
        return 5 + (10 * $weight) + (6.25 * $height) - (5 * $age);
    } else {
        return (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weight = $_POST['weight'];
    $age = $_POST['age'];
    $height = $_POST['height'];
    $gender = $_POST['gender'];
    $activity_level = (float)$_POST['activity_level'];

    // Calculate base BMR
    $bmr = calculate_bmr($gender, $weight, $height, $age);

    // Calculate adjusted BMR based on activity level
    $adjusted_bmr = $bmr * $activity_level;

    // Get current UNIX timestamp
    $unix_timestamp = time();

    // Insert fitness log with adjusted BMR into the database
    $stmt = $conn->prepare("INSERT INTO fitness_log (user_id, weight, age, height, bmr, unix_timestamp) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiidi", $user_id, $weight, $age, $height, $adjusted_bmr, $unix_timestamp);

    if ($stmt->execute()) {
        $success_message = "Fitness data logged successfully with adjusted BMR!";
    } else {
        $error_message = "Error logging fitness data: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Log</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* General styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Header styling */
        header nav {
            background-color: #333;
            padding: 1em;
            text-align: center;
        }

        header nav ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }

        header nav ul li {
            display: inline;
            margin: 0 1em;
        }

        header nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        /* Main content styling */
        .content-container {
            max-width: 500px;
            margin: 2em auto;
            background-color: white;
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .page-title {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .appointment-form label {
            font-weight: bold;
            margin-top: 10px;
        }

        .appointment-form input,
        .appointment-form select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .submit-button {
            margin-top: 20px;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .submit-button:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 10px;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
        }

        .success {
            color: green;
            margin-top: 10px;
        }

        .error {
            color: red;
            margin-top: 10px;
        }

        .twitter-share-button {
            background-color: #1DA1F2;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .twitter-share-button:hover {
            background-color: #0d95e8;
        }
    </style>
</head>
<body>
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
    <h1 class="page-title">Log Your Fitness Data</h1>

    <?php
    if (isset($success_message)) {
        echo "<p class='success'>$success_message</p>";
    }
    if (isset($error_message)) {
        echo "<p class='error'>$error_message</p>";
    }
    ?>

    <form action="fitness_log.php" method="POST" class="appointment-form">
        <label for="weight">Weight (kg):</label>
        <input type="number" id="weight" name="weight" step="0.1" required>

        <label for="height">Height (cm):</label>
        <input type="number" id="height" name="height" step="0.1" required>

        <label for="age">Age:</label>
        <input type="number" id="age" name="age" required>

        <label for="gender">Gender:</label>
        <select id="gender" name="gender" required>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>

        <label for="activity_level">Activity Level:</label>
        <select id="activity_level" name="activity_level" required>
            <option value="1.2">Sedentary</option>
            <option value="1.375">Light</option>
            <option value="1.55">Moderate</option>
            <option value="1.725">Active</option>
            <option value="1.9">Very Active</option>
        </select>
        <button type="submit" class="submit-button">Log Fitness Data</button>
    </form>

    <h2>Your Fitness Log</h2>
    <?php if ($fitness_logs): ?>
        <table>
            <thead>
            <tr>
                <th>Weight (kg)</th>
                <th>BMR</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fitness_logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['weight']) ?></td>
                    <td><?= htmlspecialchars($log['bmr']) ?></td>
                    <td><?= htmlspecialchars($log['date']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No fitness logs found.</p>
    <?php endif; ?>

    <!-- Share to Twitter Button -->
    <?php
    if ($fitness_logs) {
        $most_recent_log = $fitness_logs[0];
        $bmr = $most_recent_log['bmr'];
        echo('<a href="https://twitter.com/intent/tweet?text=I%20have%20just%20logged%20my%20fitness%20data!%20My%20BMR%20is:%20' . $bmr . '" target="_blank" class="twitter-share-button">Share to Twitter</a>');
    }
    ?>
</div>
</body>
</html>
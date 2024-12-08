<?php
global $conn;
require_once 'database_connection.php';

// Start session to check if user is logged in
session_start();

// Initialize variables
$user_type = null;
$logged_in_user_id = null;
$is_trainer = false;
$message = ""; // Store success or error message
$message_type = ""; // Success or error type for styling

// Check if session token is set, and if so, get user type
if (isset($_SESSION['username']) && isset($_SESSION['session_token'])) {
    $session_token = $_SESSION['session_token'];

    $sql_query = "SELECT user_type, user_id FROM mtuarena_db.user WHERE session_token = ?";
    $stmt = $conn->prepare($sql_query);
    $stmt->bind_param("s", $session_token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_type = $row['user_type'];
        $logged_in_user_id = $row['user_id'];

        // Checking if user is in trainers list
        $sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = ?";
        $stmt = $conn->prepare($sql_query);
        $stmt->bind_param("i", $logged_in_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $is_trainer = true;
        }
    } else {
        header('Location: login.php');
        exit();
    }
}

// Fetch all trainers' IDs and names
$trainers = [];
$sql = "
            SELECT t.trainer_id, u.full_name 
            FROM trainer t
            JOIN user u ON t.user_id = u.user_id
        ";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $trainers[] = $row;
    }
}


// Process appointment booking form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trainer_id'], $_POST['date'], $_POST['start_time']) && !isset($_POST['appointment_id'])) {
    $trainer_id = intval($_POST['trainer_id']);
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];

    // Check if the trainer is already booked at the same time
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM mtuarena_db.appointment_booking 
        WHERE trainer_id = ? AND date = ? AND start_time = ?
    ");
    $stmt->bind_param("iss", $trainer_id, $date, $start_time);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $message = "The trainer is already booked for this date and time. Please choose a different slot.";
        $message_type = "error";
    } else {
        // Insert the new booking
        $stmt = $conn->prepare("
            INSERT INTO mtuarena_db.appointment_booking (user_id, trainer_id, date, start_time) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $logged_in_user_id, $trainer_id, $date, $start_time);

        if ($stmt->execute()) {
            $message = "Appointment successfully booked!";
            $message_type = "success";
        } else {
            $message = "Failed to book the appointment. Please try again.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Process deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);

    // Ensure the user owns the appointment before deleting
    $stmt = $conn->prepare("DELETE FROM mtuarena_db.appointment_booking WHERE appointment_booking_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $appointment_id, $logged_in_user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Booking successfully deleted.";
        $message_type = "success";
    } else {
        $message = "Failed to delete the booking. Please try again.";
        $message_type = "error";
    }
    $stmt->close();
}

// Fetch user's appointments
$appointments = [];
$stmt = $conn->prepare("
    SELECT ab.appointment_booking_id, 
           ab.date, 
           ab.start_time, 
           u.full_name AS trainer_name, 
           t.office_room_number
    FROM mtuarena_db.appointment_booking ab
    JOIN mtuarena_db.trainer t ON ab.trainer_id = t.trainer_id
    JOIN mtuarena_db.user u ON t.user_id = u.user_id
    WHERE ab.user_id = ?
    ORDER BY ab.date, ab.start_time
");
$stmt->bind_param("i", $logged_in_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();

// Fetch clients' appointments if the user is a trainer
$clients_appointments = [];
if ($is_trainer) {
    // Fetch trainer's own trainer_id from the trainer table
    $stmt = $conn->prepare("SELECT trainer_id FROM mtuarena_db.trainer WHERE user_id = ?");
    $stmt->bind_param("i", $logged_in_user_id);
    $stmt->execute();
    $stmt->bind_result($trainer_id);
    $stmt->fetch();
    $stmt->close();

    // Fetch clients' appointments for the trainer
    $stmt = $conn->prepare("
        SELECT 
            ab.appointment_booking_id, 
            ab.date, 
            ab.start_time, 
            u.full_name AS client_name, 
            t.office_room_number
        FROM mtuarena_db.appointment_booking ab
        JOIN mtuarena_db.user u ON ab.user_id = u.user_id
        JOIN mtuarena_db.trainer t ON ab.trainer_id = t.trainer_id
        WHERE ab.trainer_id = ?
        ORDER BY ab.date, ab.start_time
    ");
    $stmt->bind_param("i", $trainer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $clients_appointments[] = $row;
    }
    $stmt->close();
}

// Process deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);

    if ($is_trainer) {
        // Fetch the trainer's trainer_id
        $stmt = $conn->prepare("SELECT trainer_id FROM mtuarena_db.trainer WHERE user_id = ?");
        $stmt->bind_param("i", $logged_in_user_id);
        $stmt->execute();
        $stmt->bind_result($trainer_id);
        $stmt->fetch();
        $stmt->close();

        // Ensure the appointment is associated with the trainer
        $stmt = $conn->prepare("
            DELETE FROM mtuarena_db.appointment_booking 
            WHERE appointment_booking_id = ? 
            AND trainer_id = ?
        ");
        $stmt->bind_param("ii", $appointment_id, $trainer_id);
    } else {
        // For a regular user, ensure they own the appointment
        $stmt = $conn->prepare("
            DELETE FROM mtuarena_db.appointment_booking 
            WHERE appointment_booking_id = ? 
            AND user_id = ?
        ");
        $stmt->bind_param("ii", $appointment_id, $logged_in_user_id);
    }

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Booking successfully deleted.";
        $message_type = "success";
    } else {
        $message = "Failed to delete the booking. Please try again.";
        $message_type = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .content-container {
            max-width: 800px;
            margin: 2em auto;
            background-color: white;
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .appointment-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .appointment-info {
            flex-grow: 1;
        }
        .appointment-info h3 {
            margin: 0 0 10px;
            color: #007bff;
        }
        .appointment-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        .delete-button {
            background-color: #dc3545;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            font-size: 14px;
        }
        .delete-button:hover {
            background-color: #c82333;
        }
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
            margin-left: 24.5%;
            margin-right: 24.5%;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            margin-left: 24.5%;
            margin-right: 24.5%;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            margin-left: 24.5%;
            margin-right: 24.5%;
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
<!-- Main Content -->
<div class="content-container">
    <h1 class="page-title">Book an Appointment</h1>
    <!-- Appointment Booking Form -->
    <form action="app_booking.php" method="POST" class="appointment-form">
        <label for="trainer_id">Trainer:</label>
        <select id="trainer_id" name="trainer_id" required>
            <option value="">Select Trainer</option>
            <?php foreach ($trainers as $trainer): ?>
                <option value="<?php echo htmlspecialchars($trainer['trainer_id']); ?>">
                    <?php echo htmlspecialchars($trainer['full_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="date">Date:</label>
        <input type="date" id="date" name="date" required>

        <label for="start_time">Start Time:</label>
        <input type="time" id="start_time" name="start_time" required>

        <input type="submit" value="Book Appointment" class="submit-button">
    </form>
</div>
<!-- Display Success or Error Message -->
<?php if (!empty($message)): ?>
    <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<div class="content-container">
    <!-- Display User's Appointments -->
    <h2 class="page-title">Your Appointments</h2>
    <?php if (!empty($appointments)): ?>
        <?php foreach ($appointments as $appointment): ?>
            <div class="appointment-card">
                <div class="appointment-info">
                    <h3>Trainer: <?php echo htmlspecialchars($appointment['trainer_name']); ?> - Appointment</h3>
                    <p><strong>Date:</strong> <?php echo htmlspecialchars($appointment['date']); ?></p>
                    <p><strong>Time:</strong> <?php echo htmlspecialchars($appointment['start_time']); ?>:00</p>
                    <p><strong>Location:</strong> Office Room <?php echo htmlspecialchars($appointment['office_room_number']); ?></p>
                </div>
                <form action="app_booking.php" method="POST">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_booking_id']; ?>">
                    <button type="submit" class="delete-button">Delete Booking</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No appointments found.</p>
    <?php endif; ?>
</div>
<!-- Display Clients' Appointments if the User is a Trainer -->
<?php if ($is_trainer): ?>
    <div class="content-container">
        <h2 class="page-title">Clients Appointments</h2>
        <?php if (!empty($clients_appointments)): ?>
            <?php foreach ($clients_appointments as $appointment): ?>
                <div class="appointment-card">
                    <div class="appointment-info">
                        <h3>Client: <?php echo htmlspecialchars($appointment['client_name']); ?> - Appointment</h3>
                        <p><strong>Date:</strong> <?php echo htmlspecialchars($appointment['date']); ?></p>
                        <p><strong>Time:</strong> <?php echo htmlspecialchars($appointment['start_time']); ?>:00</p>
                        <p><strong>Location:</strong> Office Room <?php echo htmlspecialchars($appointment['office_room_number']); ?></p>
                    </div>
                    <form action="app_booking.php" method="POST">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_booking_id']; ?>">
                        <button type="submit" class="delete-button">Delete Booking</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No appointments found by your clients.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
</body>
</html>

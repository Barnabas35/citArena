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
$logged_in_user_id = $row['user_id'];

// Checking if user is in trainers list
$sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$logged_in_user_id'";
$result = $conn->query($sql_query);
$row = $result->fetch_assoc();

$is_trainer = false;

if ($row) {
    $is_trainer = true;
}

// Handle filters and sorting from GET parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$type_filter = isset($_GET['type_sort']) ? $conn->real_escape_string($_GET['type_sort']) : 'all';
$club_type_filter = isset($_GET['club_type']) ? $conn->real_escape_string($_GET['club_type']) : '';
$sort_option = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'club_name';
$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';

// Pagination variables
$limit = 10; // Items per page
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1; // Current page number
$offset = ($page - 1) * $limit; // Offset for SQL

// Construct base SQL query
if ($type_filter === 'training_session_booking') {
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS 'Training Session' AS booking_type, tsb.training_session_booking_id AS booking_id, cts.date, cts.start_time, c.name AS club_name, c.club_type, CONCAT(f.building, ' - ', f.room) AS location
        FROM training_session_booking tsb
        JOIN club_training_session cts ON tsb.club_training_session_id = cts.club_training_session_id
        JOIN club c ON cts.club_id = c.club_id
        LEFT JOIN facility f ON cts.facility_id = f.facility_id
        WHERE tsb.user_id = $logged_in_user_id
    ";
} else {
    $sql = "
        SELECT 'Training Session' AS booking_type, tsb.training_session_booking_id AS booking_id, cts.date, cts.start_time, c.name AS club_name, c.club_type, CONCAT(f.building, ' - ', f.room) AS location
        FROM training_session_booking tsb
        JOIN club_training_session cts ON tsb.club_training_session_id = cts.club_training_session_id
        JOIN club c ON cts.club_id = c.club_id
        LEFT JOIN facility f ON cts.facility_id = f.facility_id
        WHERE tsb.user_id = $logged_in_user_id
    ";
}

// Handle delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking_id'], $_POST['booking_type'])) {
    $booking_id = intval($_POST['delete_booking_id']);
    $booking_type = $_POST['booking_type'];

    if ($booking_type === 'Training Session') {
        $delete_sql = "DELETE FROM training_session_booking WHERE training_session_booking_id = ? AND user_id = ?";
    } else {
        echo "Invalid booking type.";
        exit();
    }

    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $booking_id, $logged_in_user_id);
    if ($delete_stmt->execute()) {
        $message = "Booking successfully deleted.";
        $message_type = "success";
    } else {
        $message = "Failed to delete the booking. Please try again.";
        $message_type = "error";

    }
    $delete_stmt->close();
}

$message = ""; // Initialize the message variable
$message_type = ""; // Success or error type for styling

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking_id'], $_POST['booking_type'])) {
    $booking_id = intval($_POST['delete_booking_id']);
    $booking_type = $_POST['booking_type'];

    if ($booking_type === 'Training Session') {
        $delete_sql = "DELETE FROM training_session_booking WHERE training_session_booking_id = ? AND user_id = ?";
    } else {
        $message = "Invalid Booking Type. Please try again.";
    }

    if (isset($delete_sql)) {
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $booking_id, $logged_in_user_id);

        if ($delete_stmt->execute()) {
            $message = "Booking successfully deleted.";
            $message_type = "success";
        } else {
            $message = "Failed to delete the booking. Please try again.";
            $message_type = "error";
        }

        $delete_stmt->close();
    }
}

// Apply filters
$conditions = [];
if (!empty($search)) {
    $conditions[] = "c.name LIKE '%$search%'";
}
if (!empty($club_type_filter)) {
    $conditions[] = "c.club_type = '$club_type_filter'";
}
if (!empty($start_date)) {
    $conditions[] = "date >= '$start_date'";
}
if (!empty($end_date)) {
    $conditions[] = "date <= '$end_date'";
}
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Apply sorting
switch ($sort_option) {
    case 'date_soonest':
        $sql .= " ORDER BY date ASC";
        break;
    case 'date_furthest':
        $sql .= " ORDER BY date DESC";
        break;
    case 'club_name_desc':
        $sql .= " ORDER BY club_name DESC";
        break;
    default:
        $sql .= " ORDER BY club_name ASC";
        break;
}

// Apply pagination
$sql .= " LIMIT $limit OFFSET $offset";

// Execute the query
$result = $conn->query($sql);

// Fetch the total count
$total_result = $conn->query("SELECT FOUND_ROWS() AS total");
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bookings</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            font-size: 28px;
            color: #333333;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }

        input[type="text"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .search-button {
            background-color: #007BFF;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .search-button:hover {
            background-color: #0056b3;
        }

        .club-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .club-card h3 {
            font-size: 18px;
            color: #007BFF;
            margin: 0;
            margin-bottom: 10px;
        }

        .club-card p {
            font-size: 14px;
            margin: 5px 0;
            color: #555;
        }

        .delete-button-container {
            text-align: right;
        }

        .delete-button {
            background-color: #dc3545;
            color: #ffffff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .delete-button:hover {
            background-color: #a71d2a;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }

        .pagination a {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #007BFF;
        }

        .pagination a.active {
            background-color: #007BFF;
            color: white;
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
    <h1>Your Bookings</h1>
    <form method="GET" action="">
        <div class="form-group">
            <label for="search">Search by Club Name:</label>
            <input type="text" name="search" id="search" placeholder="Enter club name" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="club_type">Filter by Club Type:</label>
            <select id="club_type" name="club_type">
                <option value="">All Types</option>
                <option value="Soccer Club" <?= (isset($_GET['club_type']) && $_GET['club_type'] === 'Soccer Club') ? 'selected' : ''; ?>>Soccer Club</option>
                <option value="Basketball Club" <?= (isset($_GET['club_type']) && $_GET['club_type'] === 'Basketball Club') ? 'selected' : ''; ?>>Basketball Club</option>
                <option value="Volleyball Club" <?= (isset($_GET['club_type']) && $_GET['club_type'] === 'Volleyball Club') ? 'selected' : ''; ?>>Volleyball Club</option>
                <option value="Golf Club" <?= (isset($_GET['club_type']) && $_GET['club_type'] === 'Golf Club') ? 'selected' : ''; ?>>Golf Club</option>
                <option value="Tennis Club" <?= (isset($_GET['club_type']) && $_GET['club_type'] === 'Tennis Club') ? 'selected' : ''; ?>>Tennis Club</option>
            </select>
        </div>
        <div class="form-group">
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date); ?>">
        </div>
        <div class="form-group">
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date); ?>">
        </div>
        <div class="form-group">
            <label for="sort">Sort By:</label>
            <select id="sort" name="sort">
                <option value="club_name" <?= $sort_option === 'club_name' ? 'selected' : ''; ?>>A-Z</option>
                <option value="club_name_desc" <?= $sort_option === 'club_name_desc' ? 'selected' : ''; ?>>Z-A</option>
                <option value="date_soonest" <?= $sort_option === 'date_soonest' ? 'selected' : ''; ?>>Soonest</option>
                <option value="date_furthest" <?= $sort_option === 'date_furthest' ? 'selected' : ''; ?>>Furthest</option>
            </select>
        </div>
        <button type="submit" class="search-button">SEARCH</button>
    </form>

    <!-- Display the message here -->
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    </form>
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="club-card">
                <div class="club-details">
                    <h3><?= htmlspecialchars($row['club_name']) ?> - <?= htmlspecialchars($row['booking_type']) ?></h3>
                    <p><strong>Date:</strong> <?= htmlspecialchars($row['date']) ?></p>
                    <p><strong>Time:</strong> <?= htmlspecialchars($row['start_time']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                </div>
                <div class="delete-button-container">
                    <form method="POST" action="">
                        <input type="hidden" name="delete_booking_id" value="<?= htmlspecialchars($row['booking_id']) ?>">
                        <input type="hidden" name="booking_type" value="<?= htmlspecialchars($row['booking_type']) ?>">
                        <button type="submit" class="delete-button">Delete Booking</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No bookings found.</p>
    <?php endif; ?>
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&club_type=<?= urlencode($club_type_filter) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&sort=<?= urlencode($sort_option) ?>"
                   class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

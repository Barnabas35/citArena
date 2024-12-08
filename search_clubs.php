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

    // Checking if user is in trainers list
    $sql_query = "SELECT * FROM mtuarena_db.trainer WHERE user_id = '$logged_in_user_id'";
    $result = $conn->query($sql_query);
    $row = $result->fetch_assoc();

    $is_trainer = false;

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
    <title>MTU Clubs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
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

        input[type="text"], input[type="date"], select {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .search-button {
            background-color: #007BFF;
            color: white;
            font-size: 18px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .search-button:hover {
            background-color: #0056b3;
        }

        .club-card {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            justify-content: space-between;
            background-color: #fff;
        }

        .club-logo {
            width: 80px;
            height: 80px;
            margin-right: 20px;
            border-radius: 10px;
            object-fit: cover;
        }

        .club-details h3 {
            margin: 0;
            font-size: 20px;
            color: #007BFF;
        }

        .club-details p {
            margin: 5px 0;
            color: #555;
        }

        .view-profile-button {
            background-color: #FF0000;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .view-profile-button:hover {
            background-color: #cc0000;
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
    <h1>MTU Clubs</h1>

    <!-- Search and Sort Form -->
    <form method="GET" action="">
        <div class="form-group">
            <label for="search">Name:</label>
            <input type="text" name="search" id="search" placeholder="Search by club name"
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="club_type">Filter by Club Type:</label>
            <select name="club_type" id="club_type">
                <option value="">All Types</option>
                <option value="Soccer Club" <?php echo (isset($_GET['club_type']) && $_GET['club_type'] == 'Soccer Club') ? 'selected' : ''; ?>>Soccer Club</option>
                <option value="Basketball Club" <?php echo (isset($_GET['club_type']) && $_GET['club_type'] == 'Basketball Club') ? 'selected' : ''; ?>>Basketball Club</option>
                <option value="Volleyball Club" <?php echo (isset($_GET['club_type']) && $_GET['club_type'] == 'Volleyball Club') ? 'selected' : ''; ?>>Volleyball Club</option>
                <option value="Golf Club" <?php echo (isset($_GET['club_type']) && $_GET['club_type'] == 'Golf Club') ? 'selected' : ''; ?>>Golf Club</option>
                <option value="Tennis Club" <?php echo (isset($_GET['club_type']) && $_GET['club_type'] == 'Tennis Club') ? 'selected' : ''; ?>>Tennis Club</option>
            </select>
        </div>

        <div class="form-group">
            <label for="start_date">Filter by Start Date:</label>
            <input type="date" name="start_date" id="start_date"
                   value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
        </div>

        <div class="form-group end-date-group">
            <label for="end_date">Filter by End Date:</label>
            <input type="date" name="end_date" id="end_date"
                   value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="sort">Sort:</label>
            <select name="sort" id="sort">
                <option value="name" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name') ? 'selected' : ''; ?>>A-Z</option>
                <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_desc') ? 'selected' : ''; ?>>Z-A</option>
                <option value="calories_high" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'calories_high') ? 'selected' : ''; ?>>Calories Burned (High to Low)</option>
                <option value="calories_low" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'calories_low') ? 'selected' : ''; ?>>Calories Burned (Low to High)</option>
                <option value="date_soonest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'date_soonest') ? 'selected' : ''; ?>>Training Session Soonest</option>
                <option value="date_furthest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'date_furthest') ? 'selected' : ''; ?>>Training Session Furthest</option>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" class="search-button">SEARCH</button>
        </div>
    </form>

    <?php
    // Include the database connection file
    include 'database_connection.php';

    // Ensure $conn exists
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    // Base SQL query
    $sql = "SELECT DISTINCT club.club_id, club.name, club.expected_cal_burnt, club.profile_img_url, club.club_type 
            FROM club 
            LEFT JOIN club_training_session ON club.club_id = club_training_session.club_id";

    // Apply filters
    $conditions = [];
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $conn->real_escape_string($_GET['search']);
        $conditions[] = "club.name LIKE '%$search%'";
    }
    if (isset($_GET['club_type']) && !empty($_GET['club_type'])) {
        $club_type = $conn->real_escape_string($_GET['club_type']);
        $conditions[] = "club.club_type = '$club_type'";
    }
    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
        $start_date = $conn->real_escape_string($_GET['start_date']);
        $conditions[] = "club_training_session.date >= '$start_date'";
    }
    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
        $end_date = $conn->real_escape_string($_GET['end_date']);
        $conditions[] = "club_training_session.date <= '$end_date'";
    }

    // Combine conditions with WHERE clause
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Apply sorting
    if (isset($_GET['sort'])) {
        switch ($_GET['sort']) {
            case 'calories_high':
                $sql .= " ORDER BY club.expected_cal_burnt DESC";
                echo "<p>Sorting by Calories Burned (High to Low).</p>";
                break;
            case 'calories_low':
                $sql .= " ORDER BY club.expected_cal_burnt ASC";
                echo "<p>Sorting by Calories Burned (Low to High).</p>";
                break;
            case 'name_desc':
                $sql .= " ORDER BY club.name DESC";
                echo "<p>Sorting by Name (Z-A).</p>";
                break;
            case 'date_soonest':
                $sql .= " ORDER BY club_training_session.date ASC";
                echo "<p>Sorting by Training Session Soonest.</p>";
                break;
            case 'date_furthest':
                $sql .= " ORDER BY club_training_session.date DESC";
                echo "<p>Sorting by Training Session Furthest.</p>";
                break;
            default:
                $sql .= " ORDER BY club.name ASC"; // Default sorting
                echo "<p>Sorting by Name (A-Z).</p>";
                break;
        }
    } else {
        $sql .= " ORDER BY club.name ASC"; // Default sorting
        echo "<p>Sorting by Name (A-Z).</p>";
    }

    // Pagination logic
    $limit = 10; // Maximum items per page
    $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1; // Current page number (minimum is 1)
    $offset = ($page - 1) * $limit; // Calculate offset

    // Count total records
    $total_count_query = "SELECT COUNT(DISTINCT club.club_id) AS total_count FROM club";
    if (!empty($conditions)) {
        $total_count_query .= " WHERE " . implode(" AND ", $conditions);
    }
    $total_count_result = $conn->query($total_count_query);
    $total_count_row = $total_count_result->fetch_assoc();
    $total_count = $total_count_row['total_count'];

    $total_pages = ceil($total_count / $limit); // Calculate total pages

    // Apply LIMIT and OFFSET
    $sql .= " LIMIT $limit OFFSET $offset";

    // Execute the query
    $result = $conn->query($sql);

    // Display club cards
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div class='club-card'>";
            echo "<img src='" . htmlspecialchars($row['profile_img_url']) . "' alt='" . htmlspecialchars($row['name']) . " logo' class='club-logo'>";
            echo "<div class='club-details'>";
            echo "<h3>" . htmlspecialchars($row['name']) . "</h3>";
            echo "<p><strong>Calories Burned (Avg):</strong> " . htmlspecialchars($row['expected_cal_burnt']) . "</p>";
            echo "<p><strong>Club Type:</strong> " . htmlspecialchars($row['club_type']) . "</p>";
            echo "</div>";
            echo "<a href='club_profile.php?club_id=" . htmlspecialchars($row['club_id']) . "' class='view-profile-button'>View Profile</a>";
            echo "</div>";
        }
    } else {
        echo "<div class='club-card'><h3>No clubs found.</h3></div>";
    }

    // Pagination Links
    if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&club_type=<?= urlencode($_GET['club_type'] ?? '') ?>&start_date=<?= urlencode($_GET['start_date'] ?? '') ?>&end_date=<?= urlencode($_GET['end_date'] ?? '') ?>&sort=<?= urlencode($_GET['sort'] ?? '') ?>"
                   class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif;

    // Close connection
    $conn->close();
    ?>
</div>

</body>
</html>

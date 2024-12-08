<?php

require_once 'database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $conn;

    // Get the training session booking ID from the form submission
    $training_session_booking_id = isset($_POST['booking_id']) ? $_POST['booking_id'] : null;

    if (!$training_session_booking_id) {
        // Redirect if no session booking ID is provided
        header('Location: search_bookings.php');
        exit;
    }

    // Start the session and get the user's session token
    session_start();
    if (!isset($_SESSION['session_token'])) {
        // Redirect if session token is missing
        header('Location: login.php');
        exit;
    }

    $session_token = $_SESSION['session_token'];

    // Fetch the user from the database using a prepared statement
    $sql = "SELECT user_id FROM user WHERE session_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $session_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'] ?? null;

    if (!$user_id) {
        // Redirect if the user is not found
        header('Location: search_bookings.php');
        exit;
    }

    // Verify if the user is a trainer or staff
    $sql = "SELECT user_type FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_type = $user['user_type'];

    if ($user_type === 'Staff') {
        // Verify the booking and delete the session if the ID matches
        $sql = "SELECT club_training_session_id FROM training_session_booking WHERE training_session_booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $training_session_booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();

        if ($booking) {
            // Perform deletion of the training session if the ID is found
            $sql = "DELETE FROM club_training_session WHERE club_training_session_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $booking['club_training_session_id']);
            $stmt->execute();

            // Deleting all bookings associated with the session (this must be done through the training_session_booking_id for safe deletion)
            $sql = "DELETE FROM training_session_booking WHERE training_session_booking_id IN (SELECT training_session_booking_id FROM training_session_booking WHERE club_training_session_id = ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $booking['club_training_session_id']);
            $stmt->execute();

            // Redirect to the same page to refresh and show the updated content
            echo "<script>alert('Training Session Deleted'); window.location.href = 'search_bookings.php';</script>";
            exit; // Ensure no further code is executed
        } else {
            // If the session booking ID does not exist
            echo "<script>alert('Session booking not found or invalid.'); window.location.href = 'search_bookings.php';</script>";
        }
    } else {
        // Redirect if the user is not a trainer (staff)
        header('Location: search_bookings.php');
        exit;
    }
} else {
    // Redirect if accessed without POST request
    header('Location: search_bookings.php');
    exit;
}
?>

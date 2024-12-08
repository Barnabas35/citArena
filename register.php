<?php

require_once 'database_connection.php';

// Checking if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Getting the form data
    $full_name = $_POST['full_name'];
    $birthdate = $_POST['birthdate'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    $height = $_POST['height'];
    $user_type = $_POST['user_type'];
    $membership = $_POST['membership'];
    $username = $_POST['username'];

    // Checking if the username is already taken
    $sql_query = "SELECT * FROM mtuarena_db.user WHERE username = '$username'";

    // Need this comment or else the IDE complains about $conn not being defined
    /** @var mysqli $conn */
    $result = $conn->query($sql_query);

    // If so then set the error flag to true
    $username_taken_error = False;
    if ($result->num_rows > 0) {

        $username_taken_error = True;

    } else {
        // Hashing the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Inserting the data into the database (registering the user)
        $sql_query = "CALL mtuarena_db.add_user('$username', '$full_name', '$birthdate', '$email', '$hashed_password', '$phone_number', '$address', $height, '$user_type', '$membership')";

        // Need this comment or else the IDE complains about $conn not being defined
        /** @var mysqli $conn */
        if ($conn->query($sql_query) === TRUE) {

            // Redirecting to the login page
            header('Location: login.php');

        } else {
            echo "Error: " . $sql_query . "<br>" . $conn->error;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main>
        <div class="content-container">
            <h1 style="text-align: center">Register</h1>
            <!-- Error message if the username is already taken -->
            <?php if (isset($username_taken_error) && $username_taken_error) { ?>
                <p style="color: red;">The username is already taken! Try again.</p>
            <?php } ?>

            <!-- Form for username, full_name, birthdate, email, password, phone_number, address, height, user_type and membership -->
            <form action="register.php" method="post" class="appointment-form">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" required><br>

                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required><br>

                <label for="birthdate">Birthdate:</label>
                <input type="date" id="birthdate" name="birthdate" required><br>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required><br>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required><br>

                <label for="phone_number">Phone Number:</label>
                <input type="text" id="phone_number" name="phone_number" required><br>

                <label for="address">Address:</label>
                <input type="text" id="address" name="address" required><br>

                <label for="height">Height(cm):</label>
                <input type="number" id="height" name="height" required><br>

                <label for="user_type">User Type:</label>
                <select id="user_type" name="user_type" required>
                    <option value="Staff">Staff</option>
                    <option value="Student">Student</option>
                    <option value="Public">Public</option>
                </select><br><br>

                <label for="membership">Membership:</label>
                <select id="membership" name="membership" required>
                    <option value="One-Year">One-Year</option>
                    <option value="Open-Ended">Open-Ended</option>
                </select><br><br>

                <input type="submit" value="Register">
            </form>
        </div>
    </main>
</body>

</html>

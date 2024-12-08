<?php
require_once 'database_connection.php';

// Checking if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Getting the form data
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Error flags
    $username_error = False;
    $password_error = False;
    $locked_account_error = False;

    // Checking if the username exists
    $sql_query = "SELECT * FROM mtuarena_db.user WHERE username = '$username'";

    // Need this comment or else the IDE complains about $conn not being defined
    /** @var mysqli $conn */
    $result = $conn->query($sql_query);
    $row = $result->fetch_assoc();

    // If the user exists then get the number of failed login attempts
    if ($result->num_rows > 0) {

        $failed_login = $row['failed_login'];
        if ($failed_login >= 3) {
            $locked_account_error = True;
        }
    } else {
        $username_error = True;
    }

    // Lock out the account if the user has failed to login 3 times
    if (!$locked_account_error && !$username_error) {

        // Verifying the given password against the hashed password in the database
        if (password_verify($password, $row['hashed_password'])) {

            // Generate a session token
            $session_token = bin2hex(random_bytes(32));

            // Inserting the session token into the database
            $sql_query = "UPDATE mtuarena_db.user SET session_token = '$session_token' WHERE username = '$username'";
            $conn->query($sql_query);

            // Start session
            session_start();
            $_SESSION['username'] = $username;
            $_SESSION['session_token'] = $session_token;

            // Redirecting to the home page
            header('Location: index.php');

        } else {
            $password_error = True;

            // Incrementing the failed login attempts
            $sql_query = "UPDATE mtuarena_db.user SET failed_login = failed_login + 1 WHERE username = '$username'";
            $conn->query($sql_query);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main>
        <div class="content-container">
            <h1 style="text-align: center">Login</h1>

            <?php
            if (isset($locked_account_error) && $locked_account_error) {
                echo '<p style="color: red;">This account has been locked due to too many failed login attempts.</p>';
            }
            ?>
                <form action="login.php" method="post" class="appointment-form">

                <?php
                if (isset($username_error) && $username_error) {
                    echo '<p style="color: red;">Username does not exist.</p>';
                }
                ?>

                <label for="username">Username:</label><br>
                <input type="text" id="username" name="username" required><br>

                <?php
                if (isset($password_error) && $password_error) {
                    echo '<p style="color: red;">Incorrect password.</p>';
                }
                ?>

                <label for="password">Password:</label><br>
                <input type="password" id="password" name="password" required><br>
                <input type="submit" value="Login">
            </form>
        </div>
    </main>
</body>

</html>

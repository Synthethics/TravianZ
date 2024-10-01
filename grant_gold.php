<?php
// Database credentials
$host = 'localhost';
$dbname = 'dbname';
$user = 'dbuser';
$pass = 'dbpass';

// Create a new PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to check if user can receive more gold within the 24-hour period
function canGrantGold($pdo, $username, $gold) {
    // Calculate the total gold granted within the last 24 hours
    $query = $pdo->prepare("
        SELECT SUM(gold_granted) as total_gold 
        FROM gold_transactions 
        WHERE username = :username 
        AND grant_time >= NOW() - INTERVAL 1 DAY
    ");
    $query->bindParam(':username', $username);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    $totalGoldIn24Hours = $result['total_gold'] ?? 0;

    if (($totalGoldIn24Hours + $gold) <= 50) {
        return true; // Can grant the gold
    } else {
        return false; // Reached the limit
    }
}

// Function to grant gold and update the database
function grantGold($pdo, $username, $gold) {
    // Check if the user exists in the users table
    $query = $pdo->prepare("SELECT gold FROM s1_users WHERE username = :username");
    $query->bindParam(':username', $username);
    $query->execute();

    if ($query->rowCount() > 0) {
        // User exists, check if they can get more gold
        if (canGrantGold($pdo, $username, $gold)) {
            // User can be granted gold, update the user's total gold
            $result = $query->fetch(PDO::FETCH_ASSOC);
            $currentGold = $result['gold'];
            $newGold = $currentGold + $gold;

            // Update the user's gold in the users table
            $updateQuery = $pdo->prepare("UPDATE s1_users SET gold = :gold WHERE username = :username");
            $updateQuery->bindParam(':gold', $newGold);
            $updateQuery->bindParam(':username', $username);
            $updateQuery->execute();

            // Log the gold transaction
            $logQuery = $pdo->prepare("INSERT INTO gold_transactions (username, gold_granted) VALUES (:username, :gold)");
            $logQuery->bindParam(':username', $username);
            $logQuery->bindParam(':gold', $gold);
            $logQuery->execute();

            return "$gold gold granted to $username. Total gold: $newGold";
        } else {
            return "$username has already been granted the maximum of 50 gold in the last 24 hours.";
        }
    } else {
        return "User $username does not exist.";
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $gold = intval($_POST['gold']);

    if (!empty($username) && $gold > 0 && $gold <= 50) {
        $message = grantGold($pdo, $username, $gold);
    } else {
        $message = "Please enter a valid username and gold amount (1-50).";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grant Gold</title>
    <style>
        body {
            background-image: url('https://example.image.com/'); /* Replace with the URL of a cartoony village background */
            background-size: cover;
            background-position: center;
            font-family: Arial, sans-serif;
            color: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        h1 {
            font-size: 3em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px #000000;
        }
        form {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.7);
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        label {
            font-size: 1.2em;
        }
        input[type="text"], input[type="number"] {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1em;
            width: 100%;
            max-width: 250px;
            box-sizing: border-box;
            display: block;
            margin: 10px auto;
        }
        .button-group {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2em;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.5);
            flex: 1;
            margin: 0 5px; /* Add some space between buttons */
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        .button:hover {
            background-color: #45a049;
        }
        .back-button {
            background-color: #f44336;
        }
        .back-button:hover {
            background-color: #d32f2f;
        }
        p {
            margin-top: 20px;
            font-size: 1.2em;
            text-shadow: 1px 1px 3px #000000;
        }
    </style>
</head>
<body>

    <h1>Gold Generator</h1>
    <form method="post" action="">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required><br>

        <label for="gold">Amount of Gold (1-50):</label>
        <input type="number" id="gold" name="gold" min="1" max="50" required><br>

        <div class="button-group">
            <input type="submit" value="Grant Gold" class="button">
            <a href="/dorf1.php" class="button back-button">Back</a>
        </div>
    </form>

    <?php if (isset($message)) { echo "<p>$message</p>"; } ?>

</body>
</html>

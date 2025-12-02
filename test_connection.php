<?php
// Test database connection
require_once 'config.php';

try {
    $conn = getConnection();
    echo "<h2>Database Connection Test</h2>";
    echo "<p style='color: green;'>✓ Successfully connected to the database!</p>";
    
    // Test query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM accounting_entries");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Number of entries in the database: " . $result['count'] . "</p>";
    
    echo "<h3>Sample Data:</h3>";
    $stmt = $conn->query("SELECT * FROM accounting_entries LIMIT 5");
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>ID</th><th>Date</th><th>Description</th><th>Type</th><th>Amount</th>";
        echo "</tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "<td>" . htmlspecialchars($row['entry_type']) . "</td>";
            echo "<td>$" . htmlspecialchars($row['amount']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No entries found in the database. You may need to run the create_database.sql script.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Database Connection Test</h2>";
    echo "<p style='color: red;'>✗ Connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
}
?>

<br><br>
<a href="index.php" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Go to Main Application</a>
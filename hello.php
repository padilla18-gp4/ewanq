<?php

$host = "localhost";
$username = "root";
$password = "";
$dbname = "scheduling_system";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT id, student_id, full_name, email, phone, password, created_at FROM users";
$result = $conn->query($sql);

?>


<html>
    <body>
            <table border='3'>
                    <tbody>
                            <?php

                            if($result && $result->num_rows > 0){
                                while($row = $result->fetch_assoc()){
                                    echo "<tr>";
                                    echo "<td>" . $row["id"] . "</td>";
                                    echo "<td>" . $row["student_id"] . "</td>";
                                    echo "<td>" . $row["full_name"] . "</td>";
                                    echo "<td>" . $row["email"] . "</td>";
                                    echo "<td>" . $row["phone"] . "</td>";
                                    echo "<td>" . $row["password"] . "</td>";
                                    echo "<td>" . $row["created_at"] . "</td>";
                                    echo "</tr>";
                                }
                            } else{
                                echo "<tr><td colspan='4' > Reconds Not Found</td></tr>";
                            }

                            $conn->close();
                            ?>
                    </tbody>
            </table>
    </body>
</html>
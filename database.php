<?php

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "Scheduling_System"
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>
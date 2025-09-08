<?php include 'db.php'; $id=(int)($_GET['id']??0);
if($id){ $conn->query("DELETE FROM employees WHERE EmployeeID=$id"); }
header('Location: employees.php'); exit;

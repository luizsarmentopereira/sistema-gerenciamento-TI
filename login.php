<?php
session_start();
$_SESSION['ID'] = 1;
$_SESSION['NOME'] = 'Admin';
$_SESSION['PERFIL'] = 'MASTER';
$_SESSION['DEPARTAMENTO'] = 'T.I.';
header("Location: gerenciar.php");
exit();
?>
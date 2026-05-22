<?php

// archivo para hacer pruebas solo cerrando sesion y haciendo registros

session_start();
session_unset();
session_destroy();
header("Location: login.php"); // Redirige al login
exit();

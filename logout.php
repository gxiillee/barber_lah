<?php

require_once __DIR__ . '/bootstrap.php';

iniciarSesionSegura();
session_unset();
session_destroy();
header("Location: login.php");
exit();

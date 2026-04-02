<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

app_start_session();
session_destroy();

redirect('login.php');

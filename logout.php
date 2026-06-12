<?php
require __DIR__ . '/config.php';
session_destroy();
session_start();
flash('You have been signed out.');
redirect('index.php');

<?php
require_once 'vendor/autoload.php';
use App\Entity\User;

$user = new User();
$services = $user->getServices();
echo 'Method getServices() exists and returns: ' . get_class($services) . PHP_EOL;
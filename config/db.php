<?php

return [

    // 1. Authentication database : Connection to the Auth / Central Database
    'dbAuth' => [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=YOUR_DATABASE_NAME',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
    ],

    // 2. Application database : Connection to the Auth / Central Database
    'db' => [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=YOUR_DATABASE_NAME',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];


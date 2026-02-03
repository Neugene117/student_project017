<?php
// Mail configuration for PHPMailer (SMTP)
// Fill these values when you're ready to use Gmail App Passwords or another SMTP provider.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls', // 'tls' or 'ssl'
    'username' => 'fabrdaa@gmail.com', // your email address
    'password' => 'khzgtfxpdqmdxwhy', // your app password
    'from_email' => 'fabrdaa@gmail.com', // same as username or a verified sender
    'from_name' => 'REG Management System',
    'reply_to' => '', // optional
    'debug' => false,
];

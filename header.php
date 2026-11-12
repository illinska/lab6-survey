<?php // header.php — верхня частина сторінки (підключається через include) ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle ?? 'Анкета') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card<?= !empty($wide) ? ' wide' : '' ?>">
    <nav>
        <a href="survey.php">Анкета</a>
        <a href="admin.php">Адмін-панель</a>
    </nav>

<?php

declare(strict_types=1);

// テストパスワード：password123
$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "パスワード: $password\n";
echo "ハッシュ: $hash\n";
echo "\nこのハッシュを使用してテストユーザーを作成してください。\n";

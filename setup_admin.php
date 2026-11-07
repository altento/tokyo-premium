<?php
require __DIR__.'/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found');}
$email=trim($argv[1]??readline('Email администратора: '));$password=$argv[2]??readline('Пароль (не менее 12 символов): ');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Нужен корректный email и пароль не короче 12 символов.\n");exit(1);}$q=db()->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)');$q->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);fwrite(STDOUT,"Учётная запись администратора создана.\n");

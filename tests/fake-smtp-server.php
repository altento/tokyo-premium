<?php
declare(strict_types=1);

$portFile = $argv[1] ?? '';
$captureFile = $argv[2] ?? '';
$mode = $argv[3] ?? 'accept';
$connectedFile = $argv[4] ?? '';
$releaseFile = $argv[5] ?? '';
if ($portFile === '' || $captureFile === '') exit(64);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
if (!is_resource($server)) exit(65);
$address = stream_socket_get_name($server, false);
$port = (int)substr(strrchr((string)$address, ':'), 1);
file_put_contents($portFile, (string)$port);
$client = stream_socket_accept($server, 15);
if (!is_resource($client)) exit(66);
stream_set_timeout($client, 15);
if ($mode === 'slow') {
    if ($connectedFile !== '') file_put_contents($connectedFile, 'accepted');
    if ($releaseFile !== '') {
        for ($wait = 0; !is_file($releaseFile) && $wait < 1500; $wait++) usleep(10000);
        if (!is_file($releaseFile)) exit(67);
    } else {
        usleep(1500000);
    }
}
fwrite($client, "220 local test SMTP ready\r\n");
$inData = false;
$message = '';
while (($line = fgets($client, 4096)) !== false) {
    if ($inData) {
        if (rtrim($line, "\r\n") === '.') {
            file_put_contents($captureFile, $message);
            fwrite($client, $mode === 'reject' ? "550 test rejection\r\n" : "250 queued for test\r\n");
            $inData = false;
        } else {
            $message .= $line;
        }
        continue;
    }
    $command = strtoupper(strtok(rtrim($line, "\r\n"), ' ') ?: '');
    if ($command === 'EHLO' || $command === 'HELO') fwrite($client, "250-localhost\r\n250 SIZE 1048576\r\n");
    elseif ($command === 'MAIL') fwrite($client, "250 sender accepted\r\n");
    elseif ($command === 'RCPT') fwrite($client, "250 recipient accepted\r\n");
    elseif ($command === 'DATA') { $inData = true; fwrite($client, "354 end with dot\r\n"); }
    elseif ($command === 'QUIT') { fwrite($client, "221 bye\r\n"); break; }
    else { fwrite($client, "500 unexpected command\r\n"); }
}
fclose($client);
fclose($server);

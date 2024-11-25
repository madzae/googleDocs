<?php
$client_id = '';
$redirect_uri = 'realToken.php';
$scope = 'https://www.googleapis.com/auth/drive.readonly';
$auth_url = 'https://accounts.google.com/o/oauth2/auth?response_type=code&client_id=' . $client_id . '&redirect_uri=' . $redirect_uri . '&scope=' . $scope . '&access_type=offline&prompt=consent';
header('Location: ' . $auth_url);
exit();
?>

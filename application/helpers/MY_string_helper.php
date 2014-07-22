<?php
function hide_mail($email) {
	$mail_segments = explode('@', $email);
	$mail_segments[0] = str_repeat('*', strlen($mail_segments[0]));

	return implode('@', $mail_segments);
}

function hide_phone($phone) {
	return substr($phone, 0, -4) . '****';
}
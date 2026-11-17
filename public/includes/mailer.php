<?php
declare(strict_types=1);

/**
 * CodeStep platformasi uchun xatlar yuborish va tasdiqlash xizmati.
 */
function codeStepSendResetCode(string $email, string $code): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? 'codestep.tokhirdev.uz';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $host . '/';

    $subject = "CodeStep — Parolni qayta tiklash kodi: " . $code;
    $message = "Assalomu alaykum!\n\n"
        . "CodeStep platformasidagi hisobingiz parolini qayta tiklash so'rovi yuborildi.\n\n"
        . "Tasdiqlash kodi: " . $code . "\n\n"
        . "Ushbu kod 15 daqiqa davomida amal qiladi.\n"
        . "Agar parolni tiklashni siz so'ramagan bo'lsangiz, ushbu xatni e'tiborsiz qoldirishingiz mumkin.\n\n"
        . "Hurmat bilan,\nCodeStep jamoasi\n" . $baseUrl;

    $headers = [
        'From: CodeStep <no-reply@tokhirdev.uz>',
        'Reply-To: no-reply@tokhirdev.uz',
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $sent = false;
    if (function_exists('mail')) {
        try {
            $sent = @mail($email, $subject, $message, implode("\r\n", $headers));
        } catch (Throwable $e) {
            error_log("CodeStep Mail Error: " . $e->getMessage());
        }
    }

    $_SESSION['reset_code_notice'] = [
        'email' => $email,
        'code' => $code,
        'created_at' => time()
    ];

    return $sent;
}

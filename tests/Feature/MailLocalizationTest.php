<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

test('verification email content is entirely localized and retains its signed link', function () {
    $user = User::factory()->unverified()->create();

    $message = (new VerifyEmail)->toMail($user);
    $html = (string) $message->render();

    expect($message->subject)->toBe('Confirme seu endereço de e-mail');
    expect($message->actionText)->toBe('Confirmar e-mail');
    expect($message->actionUrl)->toContain('signature=')->toContain('expires=');
    expect($html)->toContain('Olá!', 'Confirmar e-mail', 'Atenciosamente,', 'Todos os direitos reservados.', 'copie e cole o endereço abaixo')
        ->not->toContain('Hello!', 'Regards,', 'If you did not', 'All rights reserved.', 'If you&#039;re having trouble');
});

test('password reset email content is localized including its expiration notice', function () {
    $user = User::factory()->create();

    $message = (new ResetPassword('test-reset-token'))->toMail($user);
    $html = (string) $message->render();

    expect($message->subject)->toBe('Redefina sua senha');
    expect($message->actionText)->toBe('Redefinir senha');
    expect($html)->toContain('Recebemos uma solicitação', 'expira em 60 minutos', 'Se você não solicitou', 'Todos os direitos reservados.')
        ->not->toContain('Reset Password', 'Hello!', 'Regards,', 'This password reset link');
});

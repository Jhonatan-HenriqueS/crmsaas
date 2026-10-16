<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        $message = in_array($transport, ['array', 'log', null], true)
            ? 'Solicitação recebida. O envio de e-mails está indisponível neste ambiente.'
            : 'Se existir uma conta com este e-mail, você receberá instruções para redefinir a senha.';

        return $request->wantsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}

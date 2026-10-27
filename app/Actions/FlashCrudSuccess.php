<?php

namespace App\Actions;

use Inertia\Inertia;

final class FlashCrudSuccess
{
    public static function created(string $resource, bool $feminine = false): void
    {
        self::send($resource, 'created', $feminine ? 'criada' : 'criado');
    }

    public static function updated(string $resource, bool $feminine = false, ?string $verb = null): void
    {
        self::send($resource, 'updated', $verb ?? ($feminine ? 'editada' : 'editado'));
    }

    public static function deleted(string $resource, bool $feminine = false): void
    {
        self::send($resource, 'deleted', $feminine ? 'excluída' : 'excluído');
    }

    private static function send(string $resource, string $operation, string $verb): void
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'operation' => $operation,
            'message' => "{$resource} {$verb} com sucesso!",
        ]);
    }
}

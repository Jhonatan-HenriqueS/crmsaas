<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::min(8)->max(128)->uncompromised()],
            'terms' => ['accepted'],
            'plan' => ['required', 'string', Rule::in(Account::TYPES)],
        ]);
        $validator->setCustomMessages([
            'name.required' => 'Informe seu nome.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'Use até 255 caracteres no nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'Informe uma senha.',
            'password.min' => 'Use pelo menos 8 caracteres na senha.',
            'password.max' => 'Esta senha é muito longa. Escolha uma senha menor.',
            'password.uncompromised' => 'Esta senha apareceu em vazamentos. Escolha outra senha.',
            'terms.accepted' => 'Aceite os Termos e Condições para continuar.',
            'plan.*' => 'Selecione um plano válido na página inicial.',
        ]);
        /** @var array{name: string, email: string, password: string, plan: string} $data */
        $data = $validator->validate();

        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $account = Account::create(['type' => $data['plan']]);
            $user->memberships()->create([
                'account_id' => $account->id,
                'role' => 'owner',
            ]);

            return $user;
        });
    }
}

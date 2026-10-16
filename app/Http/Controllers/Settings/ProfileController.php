<?php

namespace App\Http\Controllers\Settings;

use App\Actions\FlashCrudSuccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Account;
use App\Models\Membership;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => config('auth.email_verification_required') && $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->safe()->only(['name', 'email']));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        try {
            $request->user()->save();
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->columns !== ['email']) {
                throw $exception;
            }

            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'e-mail'])]);
        }

        FlashCrudSuccess::updated('Perfil');

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $ownedAccounts = $user->memberships()->where('role', 'owner')->select('account_id');

            if (DB::connection()->getDriverName() === 'sqlite') {
                // SQLite has no row locks: acquire its write lock before reading ownership.
                Account::whereIn('id', $ownedAccounts)->update(['updated_at' => DB::raw('updated_at')]);
            }

            $accounts = Account::whereIn('id', $ownedAccounts)->orderBy('id')->lockForUpdate()->get(['id']);

            if ($accounts->isNotEmpty() && Membership::whereIn('account_id', $accounts->modelKeys())
                ->where('role', 'owner')->where('user_id', '!=', $user->id)->distinct()->count('account_id') !== $accounts->count()) {
                throw ValidationException::withMessages([
                    'password' => 'Transfira a propriedade ou encerre a conta antes de excluir seu usuário. Você é o último proprietário.',
                ]);
            }

            Auth::logout();
            $user->delete();
        }, 5);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        FlashCrudSuccess::deleted('Conta', feminine: true);

        return redirect('/');
    }
}

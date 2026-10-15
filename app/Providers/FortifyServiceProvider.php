<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\EnsureSafeAuthenticationRedirect;
use App\Http\Middleware\ValidateRegistrationEmail;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Account;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        $this->app->booted(function (): void {
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->getByName('register.store')?->middleware(['throttle:registration', ValidateRegistrationEmail::class]);

            foreach (['login.store', 'two-factor.login.store'] as $routeName) {
                Route::getRoutes()->getByName($routeName)?->middleware(EnsureSafeAuthenticationRedirect::class);
            }
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canRegister' => Features::enabled(Features::registration()),
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(function (Request $request): Response|RedirectResponse {
            if (! config('auth.email_verification_required')) {
                return to_route('dashboard');
            }

            return Inertia::render('auth/verify-email', [
                'status' => $request->session()->get('status'),
                'emailDeliveryAvailable' => ! in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array', null], true),
            ]);
        });

        Fortify::registerView(function (Request $request): Response|RedirectResponse {
            $plan = $request->query('plan');

            if (! is_string($plan) || ! in_array($plan, Account::TYPES, true)) {
                return redirect()->to(route('home').'#planos');
            }

            return Inertia::render('auth/register', ['plan' => $plan]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)->by('registration:'.$request->ip()));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower(is_string($email) ? $email : '').'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}

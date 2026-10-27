<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\EnsureSafeAuthenticationRedirect;
use App\Http\Middleware\ValidateAuthenticationInput;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\Account;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Laravel\Fortify\Http\Responses\FailedPasswordResetResponse as InvalidPasswordResetResponse;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Laravel\Fortify\Contracts\LogoutResponse::class, LogoutResponse::class);
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetResponse::class,
            fn () => new InvalidPasswordResetResponse(PasswordBroker::INVALID_TOKEN));
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
            Route::getRoutes()->getByName('register.store')?->middleware(['throttle:registration', ValidateAuthenticationInput::class]);

            foreach (['password.email', 'password.update'] as $routeName) {
                Route::getRoutes()->getByName($routeName)?->middleware(['throttle:'.$routeName, ValidateAuthenticationInput::class]);
            }

            Route::getRoutes()->getByName('password.confirm.store')?->middleware(ValidateAuthenticationInput::class);

            foreach (['login.store', 'two-factor.login.store', 'password.confirm.store', 'register.store', 'verification.verify'] as $routeName) {
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
        foreach (['password.email' => 5, 'password.update' => 10] as $limiter => $attempts) {
            RateLimiter::for($limiter, function (Request $request) use ($limiter, $attempts): array {
                $email = $request->input('email');
                $identity = hash('sha256', is_string($email) ? Str::lower(trim($email)) : '');

                return [
                    Limit::perMinute(30)->by($limiter.':ip:'.$request->ip()),
                    Limit::perMinute($attempts)->by($limiter.':identity:'.$identity.'|'.$request->ip()),
                ];
            });
        }

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

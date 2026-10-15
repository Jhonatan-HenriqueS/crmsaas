import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, LoaderCircle } from 'lucide-react';
import { HingWordmark } from '@/components/hing-wordmark';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canRegister: boolean;
    canResetPassword: boolean;
};

const fieldClass =
    'h-12 rounded-md border-[var(--auth-border)] bg-[var(--auth-field)] px-4 text-base text-[var(--auth-text)] shadow-none placeholder:text-[var(--auth-muted)] focus-visible:border-[var(--auth-focus)] focus-visible:ring-[var(--auth-focus)]/30 motion-reduce:transition-none';

const linkClass =
    'inline-flex min-h-11 items-center rounded-sm text-sm text-[var(--auth-muted)] underline-offset-4 hover:text-[var(--auth-text)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--auth-focus)]';

export default function Login({
    status,
    canRegister,
    canResetPassword,
}: Props) {
    return (
        <main
            lang="pt-BR"
            className="login-page flex min-h-svh items-center justify-center bg-[var(--auth-bg)] px-4 py-5 text-[var(--auth-text)] sm:px-8 sm:py-10 lg:px-12"
        >
            <Head title="Entrar — HING">
                <meta
                    name="description"
                    content="Entre na sua conta para acessar o CRM."
                />
            </Head>
            <div className="grid w-full max-w-[1120px] overflow-hidden rounded-2xl border border-[var(--auth-border)] bg-[var(--auth-panel)] p-3 shadow-xl shadow-black/10 lg:min-h-[700px] lg:grid-cols-[1fr_1.04fr]">
                <aside
                    aria-label="HING CRM"
                    className="relative flex min-h-36 flex-col justify-between overflow-hidden rounded-xl bg-[var(--auth-visual)] p-6 sm:p-8 lg:min-h-full"
                >
                    <img
                        src="/hing-landscape.svg"
                        alt=""
                        className="absolute inset-0 hidden size-full object-cover lg:block"
                    />
                    <div className="relative z-10 flex flex-wrap items-center justify-between gap-4">
                        <HingWordmark />
                        <Link
                            href={home()}
                            className="inline-flex min-h-11 items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 text-xs text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white motion-reduce:transition-none"
                        >
                            Voltar ao site
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <div className="relative z-10 hidden pt-64 pb-6 text-center lg:block">
                        <p className="mx-auto max-w-xs text-3xl leading-tight font-medium tracking-tight">
                            Sua próxima oportunidade
                            <br />
                            começa aqui.
                        </p>
                        <p className="mx-auto mt-4 max-w-[260px] text-sm leading-6 text-white/75">
                            Um lugar para acompanhar
                            <br />
                            cada passo do seu negócio.
                        </p>
                        <span
                            className="mx-auto mt-9 block h-0.5 w-10 rounded-full bg-white/75"
                            aria-hidden="true"
                        />
                    </div>
                </aside>
                <section
                    aria-labelledby="login-title"
                    className="flex items-center px-3 py-9 sm:px-10 sm:py-12 lg:px-14"
                >
                    <div className="mx-auto w-full max-w-md">
                        <div className="mb-8">
                            <p className="mb-4 text-xs font-medium tracking-[.14em] text-[var(--auth-muted)] uppercase">
                                Bem-vindo de volta
                            </p>
                            <h1
                                id="login-title"
                                className="text-3xl font-medium tracking-[-.045em] sm:text-[2.6rem]"
                            >
                                Entre na sua conta
                            </h1>
                            <p className="mt-3 text-sm leading-6 text-[var(--auth-muted)]">
                                Informe seu e-mail e sua senha para continuar.
                            </p>
                        </div>
                        {status && (
                            <p
                                role="status"
                                className="mb-6 rounded-md border border-[var(--auth-border)] bg-white/5 p-4 text-sm leading-6"
                            >
                                {status}
                            </p>
                        )}
                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            resetOnError={['password']}
                            disableWhileProcessing
                        >
                            {({ processing, errors }) => (
                                <div
                                    className="space-y-5"
                                    aria-busy={processing}
                                >
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="email"
                                            className="font-normal"
                                        >
                                            E-mail
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoComplete="username"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            placeholder="voce@empresa.com.br"
                                            aria-invalid={Boolean(errors.email)}
                                            aria-describedby={
                                                errors.email
                                                    ? 'email-error'
                                                    : undefined
                                            }
                                            className={fieldClass}
                                        />
                                        <InputError
                                            id="email-error"
                                            message={errors.email}
                                            className="text-[var(--auth-error)]"
                                            role="alert"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="password"
                                            className="font-normal"
                                        >
                                            Senha
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="current-password"
                                            placeholder="Sua senha"
                                            aria-invalid={Boolean(
                                                errors.password,
                                            )}
                                            aria-describedby={
                                                errors.password
                                                    ? 'password-error'
                                                    : undefined
                                            }
                                            className={fieldClass}
                                            toggleClassName="text-[var(--auth-muted)] hover:text-[var(--auth-text)] focus-visible:ring-[var(--auth-focus)]/50"
                                        />
                                        <InputError
                                            id="password-error"
                                            message={errors.password}
                                            className="text-[var(--auth-error)]"
                                            role="alert"
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-x-4">
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                value="1"
                                                className="size-4 border-[var(--auth-muted)] focus-visible:ring-[var(--auth-focus)] data-[state=checked]:border-[var(--auth-primary)] data-[state=checked]:bg-[var(--auth-primary)] data-[state=checked]:text-white"
                                            />
                                            <Label
                                                htmlFor="remember"
                                                className="min-h-11 py-3 text-sm font-normal"
                                            >
                                                Lembrar de mim
                                            </Label>
                                        </div>
                                        {canResetPassword && (
                                            <Link
                                                href={request()}
                                                className={linkClass}
                                            >
                                                Esqueceu sua senha?
                                            </Link>
                                        )}
                                    </div>
                                    <InputError
                                        message={errors.remember}
                                        className="text-[var(--auth-error)]"
                                        role="alert"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="login-button"
                                        className="h-12 w-full rounded-md border border-white/15 bg-[var(--auth-primary)] font-medium text-white shadow-none hover:bg-[var(--auth-primary-hover)] focus-visible:ring-[var(--auth-focus)]/50 motion-reduce:transition-none"
                                    >
                                        {processing ? (
                                            <>
                                                <LoaderCircle
                                                    className="size-4 animate-spin motion-reduce:animate-none"
                                                    aria-hidden="true"
                                                />
                                                Entrando…
                                            </>
                                        ) : (
                                            'Entrar'
                                        )}
                                    </Button>
                                </div>
                            )}
                        </Form>
                        {canRegister && (
                            <p className="mt-6 text-center text-sm text-[var(--auth-muted)]">
                                Ainda não tem uma conta?{' '}
                                <Link
                                    href={home().url + '#planos'}
                                    className={`${linkClass} text-[var(--auth-text)] underline`}
                                >
                                    Criar conta
                                </Link>
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </main>
    );
}

import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { HingWordmark } from '@/components/hing-wordmark';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';
import { store } from '@/routes/register';

type Props = { plan: 'empresa' | 'agencia' };

const fieldClass =
    'h-12 rounded-md border-[var(--auth-border)] bg-[var(--auth-field)] px-4 text-[var(--auth-text)] shadow-none placeholder:text-[var(--auth-muted)] focus-visible:border-[var(--auth-focus)] focus-visible:ring-[var(--auth-focus)]/30 motion-reduce:transition-none';

export default function Register({ plan }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const planName = plan === 'empresa' ? 'Empresa' : 'Agência';

    return (
        <main
            lang="pt-BR"
            className="registration-page flex min-h-svh items-center justify-center bg-[var(--auth-bg)] px-4 py-5 text-[var(--auth-text)] sm:px-8 sm:py-10 lg:px-12"
        >
            <Head title={`Criar conta ${planName} — HING`}>
                <meta
                    name="description"
                    content={`Crie sua conta no plano ${planName} para começar a organizar sua operação comercial no CRM.`}
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
                            Uma visão clara.
                            <br />
                            Novas possibilidades.
                        </p>
                        <p className="mx-auto mt-4 max-w-[260px] text-sm leading-6 text-white/75">
                            Leads, campanhas e resultados.
                            <br />
                            Cada informação no seu lugar.
                        </p>
                        <span
                            className="mx-auto mt-9 block h-0.5 w-10 rounded-full bg-white/75"
                            aria-hidden="true"
                        />
                    </div>
                </aside>
                <section
                    aria-labelledby="register-title"
                    className="flex items-center px-3 py-9 sm:px-10 sm:py-12 lg:px-14"
                >
                    <div className="mx-auto w-full max-w-md">
                        <div className="mb-8">
                            <p className="mb-4 text-xs font-medium tracking-[.14em] text-[var(--auth-muted)] uppercase">
                                Plano {planName}
                            </p>
                            <h1
                                id="register-title"
                                className="text-3xl font-medium tracking-[-.045em] sm:text-[2.6rem]"
                            >
                                Crie sua conta
                            </h1>
                            <p className="mt-3 text-sm leading-6 text-[var(--auth-muted)]">
                                {plan === 'empresa'
                                    ? 'Organize sua operação. Acompanhe cada oportunidade.'
                                    : 'Sua agência e seus clientes, com organização desde o início.'}
                            </p>
                        </div>
                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            resetOnError={['password']}
                            disableWhileProcessing
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-5">
                                    <input
                                        type="hidden"
                                        name="plan"
                                        value={plan}
                                    />
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="name"
                                            className="text-sm font-normal"
                                        >
                                            Nome
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            autoComplete="name"
                                            required
                                            maxLength={255}
                                            placeholder="Seu nome completo"
                                            aria-invalid={Boolean(errors.name)}
                                            aria-describedby={
                                                errors.name
                                                    ? 'name-error'
                                                    : undefined
                                            }
                                            className={fieldClass}
                                        />
                                        <InputError
                                            id="name-error"
                                            message={errors.name}
                                            className="text-[var(--auth-error)]"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="email"
                                            className="text-sm font-normal"
                                        >
                                            E-mail
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autoComplete="email"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            required
                                            maxLength={255}
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
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="password"
                                            className="text-sm font-normal"
                                        >
                                            Senha
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="password"
                                                name="password"
                                                type={
                                                    showPassword
                                                        ? 'text'
                                                        : 'password'
                                                }
                                                autoComplete="new-password"
                                                required
                                                minLength={8}
                                                placeholder="Crie uma senha"
                                                aria-invalid={Boolean(
                                                    errors.password,
                                                )}
                                                aria-describedby={
                                                    errors.password
                                                        ? 'password-error'
                                                        : undefined
                                                }
                                                className={`${fieldClass} pr-12`}
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setShowPassword(
                                                        !showPassword,
                                                    )
                                                }
                                                aria-label={
                                                    showPassword
                                                        ? 'Ocultar senha'
                                                        : 'Mostrar senha'
                                                }
                                                aria-pressed={showPassword}
                                                className="absolute inset-y-0 right-0 flex min-w-12 items-center justify-center rounded-r-md text-[var(--auth-muted)] hover:text-[var(--auth-text)] focus-visible:outline-2 focus-visible:outline-[var(--auth-focus)]"
                                            >
                                                {showPassword ? (
                                                    <EyeOff
                                                        className="size-5"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <Eye
                                                        className="size-5"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </button>
                                        </div>
                                        <InputError
                                            id="password-error"
                                            message={errors.password}
                                            className="text-[var(--auth-error)]"
                                        />
                                    </div>
                                    <div>
                                        <div className="flex items-start gap-3">
                                            <Checkbox
                                                id="terms"
                                                name="terms"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.terms,
                                                )}
                                                aria-describedby={
                                                    errors.terms
                                                        ? 'terms-error'
                                                        : undefined
                                                }
                                                className="mt-3 size-4 border-[var(--auth-muted)] focus-visible:ring-[var(--auth-focus)] data-[state=checked]:border-[var(--auth-primary)] data-[state=checked]:bg-[var(--auth-primary)] data-[state=checked]:text-white"
                                            />
                                            <Label
                                                htmlFor="terms"
                                                className="block min-h-11 py-2 text-xs leading-6 font-normal"
                                            >
                                                Li e aceito os{' '}
                                                <span className="underline decoration-[var(--auth-muted)] underline-offset-4">
                                                    Termos e Condições
                                                </span>
                                            </Label>
                                        </div>
                                        <InputError
                                            id="terms-error"
                                            message={errors.terms}
                                            className="text-[var(--auth-error)]"
                                        />
                                    </div>
                                    <InputError
                                        id="plan-error"
                                        message={errors.plan}
                                        className="text-[var(--auth-error)]"
                                        role="alert"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 w-full rounded-md border border-white/15 bg-[var(--auth-primary)] font-medium text-white shadow-none hover:bg-[var(--auth-primary-hover)] focus-visible:ring-[var(--auth-focus)]/50 motion-reduce:transition-none"
                                    >
                                        {processing ? (
                                            <>
                                                <LoaderCircle
                                                    className="size-4 animate-spin motion-reduce:animate-none"
                                                    aria-hidden="true"
                                                />
                                                Criando conta…
                                            </>
                                        ) : (
                                            'Criar conta'
                                        )}
                                    </Button>
                                </div>
                            )}
                        </Form>
                        <Link
                            href={home({ query: {} }).url + '#planos'}
                            className="mt-6 inline-flex min-h-11 items-center gap-2 rounded-sm text-xs text-[var(--auth-muted)] underline-offset-4 hover:text-[var(--auth-text)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--auth-focus)]"
                        >
                            <ArrowLeft
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Comparar os planos
                        </Link>
                    </div>
                </section>
            </div>
        </main>
    );
}

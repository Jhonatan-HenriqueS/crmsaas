import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

type Props = {
    status?: string;
    emailDeliveryAvailable: boolean;
};

export default function VerifyEmail({ status, emailDeliveryAvailable }: Props) {
    return (
        <>
            <Head title="Confirme seu e-mail" />

            {!emailDeliveryAvailable ? (
                <div
                    role="status"
                    className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-center text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
                >
                    O envio de e-mails está indisponível no momento. Tente
                    novamente mais tarde.
                </div>
            ) : (
                <>
                    {status === 'verification-link-sent' && (
                        <div
                            role="status"
                            className="mb-4 text-center text-sm font-medium text-green-700 dark:text-green-400"
                        >
                            Solicitamos o envio de um novo link de confirmação
                            para o e-mail informado no cadastro.
                        </div>
                    )}
                    <p className="mb-6 text-center text-sm leading-6 text-muted-foreground">
                        Abra a mensagem de confirmação e clique em “Confirmar
                        e-mail”. Se ela não aparecer na caixa de entrada,
                        confira também a pasta de spam.
                    </p>
                </>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            type="submit"
                            disabled={processing || !emailDeliveryAvailable}
                            variant="secondary"
                        >
                            {processing && <Spinner />}
                            Reenviar e-mail de confirmação
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Sair da conta
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Confirme seu e-mail',
    description:
        'Confirme seu endereço de e-mail para continuar e acessar sua conta.',
};

import { Link } from '@inertiajs/react';
import { LayoutDashboard, Menu, Tags, Users } from 'lucide-react';
import { useState } from 'react';
import { HingWordmark } from '@/components/hing-wordmark';
import { LogoutDialog } from '@/components/logout-dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useInitials } from '@/hooks/use-initials';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { index as leadsIndex } from '@/routes/leads';
import type { User } from '@/types';

type AccountUser = Pick<User, 'name' | 'email'>;

function SidebarContent({
    user,
    onNavigate,
}: {
    user: AccountUser;
    onNavigate?: () => void;
}) {
    const initials = useInitials();
    const { isCurrentUrl } = useCurrentUrl();
    const navigation = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
        { title: 'Categorias', href: categoriesIndex(), icon: Tags },
        { title: 'Leads', href: leadsIndex(), icon: Users },
    ];

    return (
        <div className="flex min-h-full flex-col">
            <div className="px-5 pt-7">
                <div className="flex items-center gap-2.5 px-2 pb-12">
                    <HingWordmark />
                    <span className="rounded border border-white/10 px-1.5 py-0.5 text-[10px] font-medium tracking-wider text-muted-foreground">
                        CRM
                    </span>
                </div>
                <p className="px-3 pb-3 text-[10px] font-medium tracking-[0.16em] text-muted-foreground uppercase">
                    Seu espaço
                </p>
                <nav aria-label="Menu principal" className="space-y-2">
                    {navigation.map((item) => {
                        const active = isCurrentUrl(item.href);
                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                onClick={onNavigate}
                                className={cn(
                                    'flex min-h-12 items-center gap-3 rounded-xl border px-4 text-sm font-medium transition-[color,background-color,filter] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring motion-reduce:transition-none',
                                    active
                                        ? 'dashboard-nav-active bg-primary text-white hover:brightness-110'
                                        : 'border-transparent text-muted-foreground hover:bg-white/5 hover:text-foreground',
                                )}
                            >
                                <item.icon
                                    className="size-[18px]"
                                    aria-hidden="true"
                                />
                                {item.title}
                                {active && (
                                    <span
                                        className="ml-auto size-1.5 rounded-full bg-white/75"
                                        aria-hidden="true"
                                    />
                                )}
                            </Link>
                        );
                    })}
                </nav>
            </div>
            <div className="mt-auto pt-12">
                <Card className="dashboard-account m-3 gap-0 rounded-xl py-0">
                    <CardContent className="p-5">
                        <div className="flex min-w-0 items-center gap-3">
                            <Avatar className="size-10 border border-white/10">
                                <AvatarFallback className="bg-primary/25 text-sm font-semibold text-[#d3c2f7]">
                                    {initials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <p
                                    className="truncate text-sm font-medium"
                                    title={user.name}
                                >
                                    {user.name}
                                </p>
                                <p
                                    className="mt-1 truncate text-[11px] text-muted-foreground"
                                    title={user.email}
                                >
                                    {user.email}
                                </p>
                            </div>
                        </div>
                        <div className="my-3.5 h-px bg-white/[0.06]" />
                        <LogoutDialog />
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

export function DashboardSidebar({ user }: { user: AccountUser }) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <>
            <aside
                aria-label="Navegação do dashboard"
                className="fixed inset-y-0 left-0 z-30 hidden w-[248px] overflow-y-auto border-r border-white/[0.055] bg-[var(--dashboard-sidebar)] lg:block 2xl:w-[264px]"
            >
                <SidebarContent user={user} />
            </aside>
            <div className="flex h-[72px] items-center gap-4 border-b border-white/[0.055] bg-[var(--dashboard-sidebar)] px-4 sm:px-6 lg:hidden">
                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11 shrink-0 rounded-xl text-foreground"
                            aria-label="Abrir menu"
                        >
                            <Menu className="size-5" aria-hidden="true" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="left"
                        closeLabel="Fechar menu"
                        className="dashboard-theme h-dvh w-[min(19rem,calc(100vw-1.5rem))] gap-0 overflow-y-auto border-white/10 bg-[var(--dashboard-sidebar)] p-0 sm:max-w-[19rem]"
                    >
                        <SheetTitle className="sr-only">
                            Menu principal
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            Dashboard, categorias, leads e informações da conta
                            autenticada.
                        </SheetDescription>
                        <SidebarContent
                            user={user}
                            onNavigate={() => setMenuOpen(false)}
                        />
                    </SheetContent>
                </Sheet>
                <HingWordmark />
            </div>
        </>
    );
}

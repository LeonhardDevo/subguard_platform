'use client';

import { useAuth } from '@/lib/auth';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useState, useEffect, ReactNode } from 'react';

import { LuLayoutDashboard, LuUsers, LuClipboardList, LuRefreshCw, LuCreditCard } from 'react-icons/lu';

const navItems = [
    { href: '/dashboard', label: 'Dashboard', icon: <LuLayoutDashboard /> },
    { href: '/dashboard/customers', label: 'Customers', icon: <LuUsers /> },
    { href: '/dashboard/plans', label: 'Plans', icon: <LuClipboardList /> },
    { href: '/dashboard/subscriptions', label: 'Subscriptions', icon: <LuRefreshCw /> },
    { href: '/dashboard/billing', label: 'Billing', icon: <LuCreditCard /> },
];

export default function DashboardLayout({ children }: { children: ReactNode }) {
    const { user, loading, logout } = useAuth();
    const pathname = usePathname();
    const router = useRouter();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    useEffect(() => {
        setIsMobileMenuOpen(false);
    }, [pathname]);

    useEffect(() => {
        if (!loading && !user) {
            router.push('/login');
        }
    }, [user, loading, router]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-[var(--color-surface)]">
                <div className="w-8 h-8 border-2 border-[var(--color-tertiary)] border-t-transparent rounded-full animate-spin"></div>
            </div>
        );
    }

    if (!user) return null;

    const handleLogout = async () => {
        await logout();
        router.push('/login');
    };

    return (
        <div className="min-h-screen flex bg-[var(--color-surface)] relative overflow-hidden">
            {/* Ambient Background Glows */}
            <div className="absolute top-0 left-0 w-[500px] h-[500px] bg-[#3b82f6] opacity-[0.03] blur-[120px] rounded-full pointer-events-none"></div>
            <div className="absolute bottom-0 right-0 w-[600px] h-[600px] bg-[#10b981] opacity-[0.03] blur-[150px] rounded-full pointer-events-none"></div>

            {/* Mobile Overlay */}
            {isMobileMenuOpen && (
                <div
                    className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 lg:hidden cursor-pointer transition-opacity"
                    onClick={() => setIsMobileMenuOpen(false)}
                />
            )}

            {/* Main Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-50 w-72 glass border-r border-white/5 flex flex-col transform transition-transform duration-500 cubic-bezier(0.16, 1, 0.3, 1) lg:static lg:translate-x-0 ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="p-8 pb-4">
                    <h1 className="text-[24px] font-bold gradient-text tracking-tight">SubGuard</h1>
                    <p className="text-[12px] font-semibold tracking-[0.05em] uppercase text-[var(--color-on-surface-variant)] mt-1">{user.company_name || 'Dashboard'}</p>
                </div>

                <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                    {navItems.map((item, idx) => {
                        const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href));
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`stagger-enter flex items-center gap-3 px-4 py-3 rounded-lg text-[14px] font-medium transition-all duration-300 ${isActive
                                    ? 'bg-[var(--color-tertiary-container)] text-[var(--color-tertiary)] border border-[var(--color-tertiary)]/20 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.1)]'
                                    : 'text-[var(--color-on-surface-variant)] hover:text-white hover:bg-white/5'
                                    }`}
                                style={{ transitionDelay: `${idx * 40}ms` }}
                            >
                                <span className="text-lg opacity-80">{item.icon}</span>
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="p-4 border-t border-white/5 bg-black/10">
                    <div className="flex items-center gap-3 px-4 py-2 mb-3 rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
                        <div className="w-9 h-9 rounded-full bg-gradient-to-br from-[var(--color-tertiary)] to-[#3b82f6] flex items-center justify-center text-sm font-bold text-black shadow-lg">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-[14px] font-medium text-white truncate tracking-tight">{user.name}</p>
                            <p className="text-[12px] text-[var(--color-on-surface-variant)] truncate">{user.email}</p>
                        </div>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="w-full px-4 py-2.5 text-[14px] font-medium text-[var(--color-on-surface-variant)] hover:text-[var(--color-error)] hover:bg-[var(--color-error-container)]/50 rounded-lg transition-all border border-transparent hover:border-[var(--color-error)]/20"
                    >
                        Sign Out
                    </button>
                </div>
            </aside>

            {/* Mobile Animated Hamburger Button */}
            <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="lg:hidden fixed top-4 right-4 z-[60] flex flex-col justify-center items-center w-10 h-10 bg-[var(--color-surface-container-highest)]/80 backdrop-blur-md rounded-lg border border-white/10 focus:outline-none shadow-lg shadow-black/50"
            >
                <div className="relative w-5 h-[14px]">
                    <span className={`absolute left-0 top-0 w-5 h-[2px] bg-white transition-all duration-300 origin-left ${isMobileMenuOpen ? 'rotate-45 translate-x-0.5 -translate-y-[1px]' : ''}`}></span>
                    <span className={`absolute left-0 top-1/2 -translate-y-1/2 w-5 h-[2px] bg-white transition-all duration-300 ${isMobileMenuOpen ? 'opacity-0 scale-x-0' : 'opacity-100 scale-x-100'}`}></span>
                    <span className={`absolute left-0 bottom-0 w-5 h-[2px] bg-white transition-all duration-300 origin-left ${isMobileMenuOpen ? '-rotate-45 translate-x-0.5 translate-y-[1px]' : ''}`}></span>
                </div>
            </button>

            {/* Main Content */}
            <main className="flex-1 overflow-auto w-full relative z-10">
                {/* Mobile Header matching space for hamburger */}
                <div className="lg:hidden h-16 border-b border-white/5 glass flex items-center px-6 sticky top-0 z-30">
                    <h1 className="text-xl font-bold gradient-text tracking-tight">SubGuard</h1>
                </div>
                <div className="p-6 md:p-10 max-w-[1440px] mx-auto">
                    {children}
                </div>
            </main>
        </div>
    );
}

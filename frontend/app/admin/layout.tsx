'use client';

import { useAuth } from '@/lib/auth';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useState, useEffect, ReactNode } from 'react';
import { LuLayoutDashboard, LuUsers, LuActivity, LuLogOut, LuArrowLeft, LuSlidersHorizontal } from 'react-icons/lu';

const navItems = [
    { href: '/admin', label: 'Admin Dashboard', icon: <LuLayoutDashboard /> },
    { href: '/admin/users', label: 'Tenants & Users', icon: <LuUsers /> },
    { href: '/admin/webhooks', label: 'Webhook Logs', icon: <LuActivity /> },
    { href: '/admin/settings', label: 'System Settings', icon: <LuSlidersHorizontal /> },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { user, loading, logout } = useAuth();
    const pathname = usePathname();
    const router = useRouter();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    useEffect(() => {
        setIsMobileMenuOpen(false);
    }, [pathname]);

    useEffect(() => {
        if (!loading) {
            if (!user) {
                router.push('/login');
            } else if (user.role !== 'admin') {
                router.push('/dashboard');
            }
        }
    }, [user, loading, router]);

    if (loading || !user || user.role !== 'admin') {
        return (
            <div className="min-h-screen flex flex-col items-center justify-center bg-gray-950 gap-4">
                <div className="w-10 h-10 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                <p className="text-gray-400 text-sm font-medium animate-pulse">Authenticating Admin Session...</p>
            </div>
        );
    }

    const handleLogout = async () => {
        await logout();
        router.push('/login');
    };

    return (
        <div className="min-h-screen flex bg-gray-950 text-gray-100">
            {/* Mobile Overlay */}
            {isMobileMenuOpen && (
                <div
                    className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden cursor-pointer"
                    onClick={() => setIsMobileMenuOpen(false)}
                />
            )}

            {/* Admin Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-50 w-66 glass border-r border-red-500/10 flex flex-col transform transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="p-6 border-b border-gray-800/50">
                    <div className="flex items-center gap-2">
                        <span className="text-xs px-2 py-0.5 rounded-md bg-red-500/20 text-red-400 border border-red-500/30 font-bold uppercase tracking-wider">
                            Admin
                        </span>
                    </div>
                    <h1 className="text-2xl font-bold gradient-text mt-1">SubGuard</h1>
                    <p className="text-xs text-gray-500 mt-1">Control Panel</p>
                </div>

                <nav className="flex-1 p-4 space-y-1">
                    {navItems.map((item) => {
                        const isActive = pathname === item.href || (item.href !== '/admin' && pathname.startsWith(item.href));
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 ${isActive
                                    ? 'bg-red-500/15 text-red-400 border border-red-500/20 shadow-sm shadow-red-500/5'
                                    : 'text-gray-400 hover:text-white hover:bg-gray-800/40'
                                    }`}
                            >
                                <span className="text-lg">{item.icon}</span>
                                {item.label}
                            </Link>
                        );
                    })}

                    <div className="pt-4 border-t border-gray-800/30 mt-4">
                        <Link
                            href="/dashboard"
                            className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-950/20 border border-transparent hover:border-indigo-500/10 transition-all duration-200"
                        >
                            <span className="text-lg"><LuArrowLeft /></span>
                            Tenant Dashboard
                        </Link>
                    </div>
                </nav>

                <div className="p-4 border-t border-gray-800/50">
                    <div className="flex items-center gap-3 px-4 py-2 mb-3">
                        <div className="w-8 h-8 rounded-full bg-gradient-to-br from-red-500 to-amber-500 flex items-center justify-center text-sm font-bold text-white shadow-md shadow-red-500/25">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-white truncate">{user.name}</p>
                            <p className="text-xs text-gray-500 truncate">{user.email}</p>
                        </div>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="w-full px-4 py-2.5 text-sm text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all border border-transparent hover:border-red-500/20"
                    >
                        <span className="flex items-center justify-center gap-2">
                            <LuLogOut className="text-sm" /> Sign Out
                        </span>
                    </button>
                </div>
            </aside>

            {/* Mobile Animated Hamburger Button */}
            <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="lg:hidden fixed top-3 right-4 z-[60] flex flex-col justify-center items-center w-10 h-10 bg-gray-900/80 backdrop-blur-md rounded-xl border border-gray-700/50 focus:outline-none shadow-lg shadow-black/50"
            >
                <div className="relative w-5 h-[14px]">
                    <span className={`absolute left-0 top-0 w-5 h-0.5 bg-gray-300 transition-all duration-300 origin-left ${isMobileMenuOpen ? 'rotate-45 translate-x-0.5 -translate-y-[1px]' : ''}`}></span>
                    <span className={`absolute left-0 top-1/2 -translate-y-1/2 w-5 h-0.5 bg-gray-300 transition-all duration-300 ${isMobileMenuOpen ? 'opacity-0 scale-x-0' : 'opacity-100 scale-x-100'}`}></span>
                    <span className={`absolute left-0 bottom-0 w-5 h-0.5 bg-gray-300 transition-all duration-300 origin-left ${isMobileMenuOpen ? '-rotate-45 translate-x-0.5 translate-y-[1px]' : ''}`}></span>
                </div>
            </button>

            {/* Main Content */}
            <main className="flex-1 overflow-auto w-full">
                {/* Mobile Header matching space for hamburger */}
                <div className="lg:hidden h-16 border-b border-gray-800/50 glass flex items-center px-6 sticky top-0 z-30">
                    <div className="flex items-center gap-2">
                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-red-500/20 text-red-400 border border-red-500/30 font-bold uppercase tracking-wider">
                            Admin
                        </span>
                        <h1 className="text-xl font-bold gradient-text">SubGuard</h1>
                    </div>
                </div>
                <div className="p-4 md:p-8">
                    {children}
                </div>
            </main>
        </div>
    );
}

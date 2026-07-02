'use client';

import { useState, FormEvent } from 'react';
import { useAuth } from '@/lib/auth';
import Link from 'next/link';
import { useRouter } from 'next/navigation';

export default function ApiLoginPage() {
    const { login } = useAuth();
    const router = useRouter();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await login(email, password);
            router.push('/onboarding');
        } catch (err: unknown) {
            setError(err instanceof Error ? err.message : 'Login failed');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center px-4 relative overflow-hidden">
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-[#3b82f6] opacity-[0.08] blur-[100px] rounded-full pointer-events-none"></div>

            <div className="w-full max-w-md stagger-enter delay-1 relative z-10">
                <div className="text-center mb-8">
                    <h1 className="text-[32px] font-bold gradient-text mb-2 tracking-tight">SubGuard</h1>
                    <p className="text-[var(--color-on-surface-variant)] text-[16px] tracking-tight">Subscription Management Platform</p>
                </div>

                <div className="glass-modal rounded-2xl p-8">
                    <h2 className="text-2xl font-semibold text-white mb-6 tracking-tight">Welcome back</h2>

                    {error && (
                        <div className="mb-4 p-3 bg-[var(--color-error-container)] border border-[var(--color-error)]/30 rounded-lg text-[var(--color-error)] text-sm stagger-enter">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="stagger-enter delay-2">
                            <label className="block text-xs font-semibold uppercase tracking-[0.05em] text-[var(--color-on-surface-variant)] mb-1.5">Email</label>
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                className="glass-input w-full px-4 py-3 rounded-lg text-white placeholder-[var(--color-on-surface-variant)]/50 focus:outline-none transition-all"
                                placeholder="you@company.com"
                            />
                        </div>

                        <div className="stagger-enter delay-3">
                            <label className="block text-xs font-semibold uppercase tracking-[0.05em] text-[var(--color-on-surface-variant)] mb-1.5">Password</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                className="glass-input w-full px-4 py-3 rounded-lg text-white placeholder-[var(--color-on-surface-variant)]/50 focus:outline-none transition-all"
                                placeholder="••••••••"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="stagger-enter delay-4 mt-2 w-full py-3 bg-[var(--color-tertiary-container)] text-[var(--color-tertiary)] font-semibold rounded-lg hover:bg-[var(--color-tertiary-container)]/80 focus:outline-none focus:ring-2 focus:ring-[var(--color-tertiary)] transition-all disabled:opacity-50 disabled:cursor-not-allowed border border-[var(--color-tertiary)]/20"
                        >
                            {loading ? (
                                <span className="flex items-center justify-center gap-2">
                                    <span className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                                    Signing in...
                                </span>
                            ) : 'Sign In'}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-[var(--color-on-surface-variant)] text-sm stagger-enter delay-4">
                        Don&apos;t have an account?{' '}
                        <Link href="/api/register" className="text-[var(--color-tertiary)] hover:underline font-medium transition-colors">
                            Create one
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}

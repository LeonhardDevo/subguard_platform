'use client';

import { useAuth } from '@/lib/auth';
import { useRouter } from 'next/navigation';
import { useState, useEffect } from 'react';
import { api } from '@/lib/api';

interface OnboardingStatus {
    onboarding: {
        id: number;
        status: string;
        stripe_account_id: string | null;
        stripe_onboarding_complete: boolean;
        stripe_capabilities_enabled: boolean;
    } | null;
    is_complete: boolean;
}

export default function OnboardingPage() {
    const { user, loading: authLoading } = useAuth();
    const router = useRouter();
    const [status, setStatus] = useState<OnboardingStatus | null>(null);
    const [loading, setLoading] = useState(true);
    const [connecting, setConnecting] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!authLoading && !user) {
            router.push('/api/login');
        }
    }, [user, authLoading, router]);

    useEffect(() => {
        if (user) {
            api.getOnboardingStatus()
                .then((res) => setStatus(res.data))
                .catch(() => setStatus(null))
                .finally(() => setLoading(false));
        }
    }, [user]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('account_id')) {
            api.completeOnboarding()
                .then((res) => setStatus({ onboarding: res.data.onboarding, is_complete: res.data.onboarding.status === 'completed' }))
                .catch(() => {});
        }
    }, []);

    const handleConnectStripe = async () => {
        setConnecting(true);
        setError('');
        try {
            const result = await api.initiateStripeConnect();
            window.location.href = result.data.account_link_url;
        } catch (err: unknown) {
            setError(err instanceof Error ? err.message : 'Failed to initiate Stripe Connect');
            setConnecting(false);
        }
    };

    if (authLoading || loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-[var(--color-surface)]">
                <div className="w-8 h-8 border-2 border-[var(--color-tertiary)] border-t-transparent rounded-full animate-spin"></div>
            </div>
        );
    }

    if (!user) return null;

    const isComplete = status?.is_complete;
    const isStripeConnected = status?.onboarding?.status === 'stripe_connected';

    return (
        <div className="min-h-screen flex items-center justify-center px-4" style={{
            background: 'radial-gradient(ellipse at top, #1a1a3e 0%, #0a0a1a 50%, #000 100%)'
        }}>
            <div className="w-full max-w-lg">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold gradient-text mb-2">SubGuard</h1>
                    <p className="text-gray-400">Complete your provider setup</p>
                </div>

                <div className="glass rounded-2xl p-8">
                    <h2 className="text-2xl font-semibold text-white mb-6">Provider Onboarding</h2>

                    {error && (
                        <div className="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
                            {error}
                        </div>
                    )}

                    <div className="space-y-6">
                        <div className="flex items-start gap-4">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${isComplete ? 'bg-green-500/20 text-green-400' : 'bg-gray-700/50 text-gray-500'}`}>
                                {isComplete ? (
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                ) : (
                                    <span className="text-sm font-medium">1</span>
                                )}
                            </div>
                            <div className="flex-1">
                                <h3 className="text-white font-medium mb-1">Connect Stripe Account</h3>
                                <p className="text-gray-400 text-sm mb-3">
                                    Link your Stripe account to start receiving payments from subscribers.
                                </p>
                                {!isComplete && (
                                    <button
                                        onClick={handleConnectStripe}
                                        disabled={connecting}
                                        className="px-4 py-2 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white font-medium rounded-lg hover:from-indigo-500 hover:to-cyan-500 transition-all disabled:opacity-50"
                                    >
                                        {connecting ? 'Connecting...' : isStripeConnected ? 'Reconnect Stripe' : 'Connect with Stripe'}
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="flex items-start gap-4">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${isComplete ? 'bg-green-500/20 text-green-400' : 'bg-gray-700/50 text-gray-500'}`}>
                                {isComplete ? (
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                ) : (
                                    <span className="text-sm font-medium">2</span>
                                )}
                            </div>
                            <div>
                                <h3 className="text-white font-medium mb-1">Account Ready</h3>
                                <p className="text-gray-400 text-sm">
                                    Once your Stripe account is verified, you can start creating plans and accepting subscribers.
                                </p>
                            </div>
                        </div>
                    </div>

                    {isComplete && (
                        <div className="mt-6 p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                            <p className="text-green-400 text-sm font-medium">
                                Your account is set up! You can now proceed to the dashboard.
                            </p>
                            <button
                                onClick={() => router.push('/dashboard')}
                                className="mt-3 px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-500 transition-all"
                            >
                                Go to Dashboard
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

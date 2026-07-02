'use client';

import { useState, useEffect } from 'react';
import { api, AdminSettings } from '@/lib/api';
import { 
    LuSlidersHorizontal, LuCircleCheck, LuTriangleAlert, 
    LuSave, LuPercent, LuUsers, LuFlame, LuCoins, LuPlay,
    LuShield, LuShieldCheck, LuKeyRound, LuLock, LuCopy
} from 'react-icons/lu';

export default function AdminSettingsPage() {
    const [settings, setSettings] = useState<AdminSettings>({
        platform_commission_rate: 1.0,
        default_free_tier_limit: 50,
        maintenance_mode: false,
        stripe_mock_mode: false
    });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    // 2FA state
    const [twoFactorEnabled, setTwoFactorEnabled] = useState(false);
    const [twoFactorLoading, setTwoFactorLoading] = useState(true);
    const [twoFactorSetup, setTwoFactorSetup] = useState<{ secret: string; otpauth_url: string } | null>(null);
    const [twoFactorCode, setTwoFactorCode] = useState('');
    const [disableCode, setDisableCode] = useState('');
    const [twoFactorActionLoading, setTwoFactorActionLoading] = useState(false);

    const fetchSettings = async () => {
        setLoading(true);
        try {
            const res = await api.getAdminSettings();
            if (res.data) {
                // Ensure correct types are parsed
                setSettings({
                    platform_commission_rate: Number(res.data.platform_commission_rate) || 1.0,
                    default_free_tier_limit: Number(res.data.default_free_tier_limit) || 50,
                    maintenance_mode: Boolean(res.data.maintenance_mode),
                    stripe_mock_mode: Boolean(res.data.stripe_mock_mode)
                });
            }
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to load system settings.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSettings();
        fetchTwoFactorStatus();
    }, []);

    const fetchTwoFactorStatus = async () => {
        setTwoFactorLoading(true);
        try {
            const res = await api.getAdminTwoFactorStatus();
            setTwoFactorEnabled(res.data.two_factor_enabled);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to load 2FA status.' });
        } finally {
            setTwoFactorLoading(false);
        }
    };

    const handleSetupTwoFactor = async () => {
        setTwoFactorActionLoading(true);
        setToast(null);
        try {
            const res = await api.setupAdminTwoFactor();
            setTwoFactorSetup(res.data);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to start 2FA setup.' });
        } finally {
            setTwoFactorActionLoading(false);
        }
    };

    const handleConfirmTwoFactor = async () => {
        if (!twoFactorSetup || twoFactorCode.length !== 6) return;
        setTwoFactorActionLoading(true);
        setToast(null);
        try {
            await api.confirmAdminTwoFactor(twoFactorSetup.secret, twoFactorCode);
            setToast({ type: 'success', message: 'Two-factor authentication enabled successfully.' });
            setTwoFactorSetup(null);
            setTwoFactorCode('');
            setTwoFactorEnabled(true);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to confirm 2FA setup.' });
        } finally {
            setTwoFactorActionLoading(false);
        }
    };

    const handleDisableTwoFactor = async () => {
        if (disableCode.length !== 6) return;
        setTwoFactorActionLoading(true);
        setToast(null);
        try {
            await api.disableAdminTwoFactor(disableCode);
            setToast({ type: 'success', message: 'Two-factor authentication disabled.' });
            setDisableCode('');
            setTwoFactorEnabled(false);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to disable 2FA.' });
        } finally {
            setTwoFactorActionLoading(false);
        }
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setToast(null);

        // Validation
        if (settings.platform_commission_rate < 0 || settings.platform_commission_rate > 100) {
            setToast({ type: 'error', message: 'Commission rate must be between 0% and 100%.' });
            setSaving(false);
            return;
        }

        if (settings.default_free_tier_limit < 0) {
            setToast({ type: 'error', message: 'Default free tier subscriber limit must be a positive integer.' });
            setSaving(false);
            return;
        }

        try {
            const res = await api.updateAdminSettings(settings);
            setToast({ type: 'success', message: res.message || 'System settings saved successfully.' });
            if (res.data) {
                setSettings({
                    platform_commission_rate: Number(res.data.platform_commission_rate),
                    default_free_tier_limit: Number(res.data.default_free_tier_limit),
                    maintenance_mode: Boolean(res.data.maintenance_mode),
                    stripe_mock_mode: Boolean(res.data.stripe_mock_mode)
                });
            }
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to update system settings.' });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-6 max-w-4xl">
            <div>
                <h1 className="text-3xl font-bold text-white">System Settings</h1>
                <p className="text-gray-400 mt-1">Configure platform-wide defaults, billing commission rates, and server states</p>
            </div>

            {/* Notifications */}
            {toast && (
                <div className={`p-4 rounded-xl border flex items-center gap-3 animate-fade-in ${
                    toast.type === 'success' 
                        ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' 
                        : 'bg-red-500/15 text-red-400 border-red-500/30'
                }`}>
                    {toast.type === 'success' ? <LuCircleCheck className="text-lg flex-shrink-0" /> : <LuTriangleAlert className="text-lg flex-shrink-0" />}
                    <div className="text-sm flex-1">{toast.message}</div>
                    <button onClick={() => setToast(null)} className="text-xs text-gray-400 hover:text-white uppercase font-bold tracking-wider px-2 py-1">Dismiss</button>
                </div>
            )}

            {loading ? (
                <div className="glass rounded-2xl p-6 border border-gray-800 space-y-6">
                    {[1, 2, 3, 4].map(i => (
                        <div key={i} className="space-y-2">
                            <div className="skeleton h-4 w-32"></div>
                            <div className="skeleton h-10 w-full"></div>
                        </div>
                    ))}
                </div>
            ) : (
                <form onSubmit={handleSave} className="space-y-6">
                    {/* Billing Configurations Card */}
                    <div className="glass rounded-2xl p-6 border border-gray-800 space-y-6">
                        <div className="flex items-center gap-3 border-b border-gray-800/60 pb-3">
                            <LuCoins className="text-red-400 text-lg" />
                            <h3 className="text-lg font-bold text-white">Billing & Commission Rules</h3>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Commission Rate */}
                            <div className="space-y-2">
                                <label className="text-sm text-gray-300 font-medium flex items-center gap-2">
                                    <LuPercent className="text-gray-500 text-xs" />
                                    Platform Commission Rate (%)
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={settings.platform_commission_rate}
                                        onChange={(e) => setSettings({ ...settings, platform_commission_rate: parseFloat(e.target.value) || 0 })}
                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-red-500/40 text-white font-mono"
                                        required
                                    />
                                    <span className="absolute right-4 top-2.5 text-xs text-gray-500 font-semibold">%</span>
                                </div>
                                <p className="text-xs text-gray-500">
                                    The percentage charged to business owners on every successful payment processed on SubGuard. Defaults to <span className="text-gray-400 font-semibold">1.0%</span>.
                                </p>
                            </div>

                            {/* Subscriber Limit */}
                            <div className="space-y-2">
                                <label className="text-sm text-gray-300 font-medium flex items-center gap-2">
                                    <LuUsers className="text-gray-500 text-xs" />
                                    Default Free Subscriber Limit
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100000"
                                    value={settings.default_free_tier_limit}
                                    onChange={(e) => setSettings({ ...settings, default_free_tier_limit: parseInt(e.target.value) || 0 })}
                                    className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-red-500/40 text-white font-mono"
                                    required
                                />
                                <p className="text-xs text-gray-500">
                                    Maximum number of active clients allowed for free tier business accounts unless individually overridden. Defaults to <span className="text-gray-400 font-semibold">50</span>.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Server Flags & Environment Card */}
                    <div className="glass rounded-2xl p-6 border border-gray-800 space-y-6">
                        <div className="flex items-center gap-3 border-b border-gray-800/60 pb-3">
                            <LuSlidersHorizontal className="text-red-400 text-lg" />
                            <h3 className="text-lg font-bold text-white">System Controls</h3>
                        </div>

                        <div className="space-y-6">
                            {/* Maintenance Mode Toggle */}
                            <div className="flex items-start justify-between gap-4 p-4 rounded-xl bg-gray-900/10 border border-gray-800/40 hover:bg-gray-900/20 transition-all">
                                <div className="space-y-1">
                                    <span className="text-sm font-semibold text-white flex items-center gap-2">
                                        <LuFlame className="text-amber-500" />
                                        Platform Maintenance Mode
                                    </span>
                                    <p className="text-xs text-gray-400 max-w-xl">
                                        Locks out all non-administrator users. Endpoints will return HTTP 503 Service Unavailable, and background operations will pause. Use during core system upgrades.
                                    </p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer mt-1">
                                    <input 
                                        type="checkbox" 
                                        checked={settings.maintenance_mode}
                                        onChange={(e) => setSettings({ ...settings, maintenance_mode: e.target.checked })}
                                        className="sr-only peer"
                                    />
                                    <div className="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500 peer-checked:after:bg-white"></div>
                                </label>
                            </div>

                            {/* Stripe Mock Mode Toggle */}
                            <div className="flex items-start justify-between gap-4 p-4 rounded-xl bg-gray-900/10 border border-gray-800/40 hover:bg-gray-900/20 transition-all">
                                <div className="space-y-1">
                                    <span className="text-sm font-semibold text-white flex items-center gap-2">
                                        <LuPlay className="text-indigo-400" />
                                        Stripe Sandbox / Mock API Mode
                                    </span>
                                    <p className="text-xs text-gray-400 max-w-xl">
                                        Bypasses external Stripe HTTP requests, simulating successful API results and dispatching local mock webhook triggers. Useful for sandbox testing or when Stripe API has outages.
                                    </p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer mt-1">
                                    <input 
                                        type="checkbox" 
                                        checked={settings.stripe_mock_mode}
                                        onChange={(e) => setSettings({ ...settings, stripe_mock_mode: e.target.checked })}
                                        className="sr-only peer"
                                    />
                                    <div className="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500 peer-checked:after:bg-white"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Security Card */}
                    <div className="glass rounded-2xl p-6 border border-gray-800 space-y-6">
                        <div className="flex items-center gap-3 border-b border-gray-800/60 pb-3">
                            <LuShield className="text-red-400 text-lg" />
                            <h3 className="text-lg font-bold text-white">Security</h3>
                        </div>

                        {twoFactorLoading ? (
                            <div className="space-y-4">
                                <div className="skeleton h-10 w-full"></div>
                                <div className="skeleton h-10 w-1/2"></div>
                            </div>
                        ) : twoFactorEnabled ? (
                            <div className="space-y-4">
                                <div className="flex items-center gap-3 p-4 rounded-xl bg-emerald-950/10 border border-emerald-500/20">
                                    <LuShieldCheck className="text-emerald-400 text-lg" />
                                    <span className="text-sm font-semibold text-emerald-400">Two-factor authentication is enabled</span>
                                </div>

                                <div className="space-y-3 p-4 rounded-xl bg-gray-900/10 border border-gray-800/40">
                                    <span className="text-sm font-semibold text-white flex items-center gap-2">
                                        <LuLock className="text-red-400" />
                                        Disable Two-Factor Authentication
                                    </span>
                                    <p className="text-xs text-gray-400">Enter your current 6-digit authenticator code to disable 2FA.</p>
                                    <div className="flex items-center gap-3">
                                        <input
                                            type="text"
                                            maxLength={6}
                                            pattern="[0-9]*"
                                            inputMode="numeric"
                                            value={disableCode}
                                            onChange={(e) => setDisableCode(e.target.value.replace(/\D/g, ''))}
                                            placeholder="000000"
                                            className="w-40 bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-red-500/40 text-white font-mono text-center tracking-widest"
                                        />
                                        <button
                                            type="button"
                                            disabled={disableCode.length !== 6 || twoFactorActionLoading}
                                            onClick={handleDisableTwoFactor}
                                            className="flex items-center gap-2 px-5 py-2.5 bg-red-500/20 hover:bg-red-500 hover:text-white border border-red-500/30 hover:border-transparent text-red-400 text-sm font-bold rounded-xl transition-all disabled:opacity-50"
                                        >
                                            {twoFactorActionLoading ? (
                                                <div className="w-4 h-4 border border-current border-t-transparent rounded-full animate-spin"></div>
                                            ) : (
                                                <LuLock />
                                            )}
                                            Disable
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ) : twoFactorSetup ? (
                            <div className="space-y-4">
                                <div className="p-4 rounded-xl bg-gray-900/10 border border-gray-800/40 space-y-4">
                                    <div className="space-y-2">
                                        <label className="text-sm text-gray-300 font-medium flex items-center gap-2">
                                            <LuKeyRound className="text-gray-500 text-xs" />
                                            Secret Key
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <code className="flex-1 bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-sm text-white font-mono break-all">
                                                {twoFactorSetup.secret}
                                            </code>
                                            <button
                                                type="button"
                                                onClick={() => navigator.clipboard.writeText(twoFactorSetup.secret)}
                                                className="p-2.5 bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white rounded-xl border border-gray-800 transition-all"
                                                title="Copy secret"
                                            >
                                                <LuCopy />
                                            </button>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm text-gray-300 font-medium flex items-center gap-2">
                                            <LuCircleCheck className="text-gray-500 text-xs" />
                                            otpauth URL
                                        </label>
                                        <div className="bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-xs text-gray-400 font-mono break-all">
                                            {twoFactorSetup.otpauth_url}
                                        </div>
                                        <p className="text-xs text-gray-500">Add this URL to your authenticator app manually or scan the QR code.</p>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm text-gray-300 font-medium flex items-center gap-2">
                                            <LuLock className="text-gray-500 text-xs" />
                                            Verification Code
                                        </label>
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="text"
                                                maxLength={6}
                                                pattern="[0-9]*"
                                                inputMode="numeric"
                                                value={twoFactorCode}
                                                onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D/g, ''))}
                                                placeholder="000000"
                                                className="w-40 bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-red-500/40 text-white font-mono text-center tracking-widest"
                                            />
                                            <button
                                                type="button"
                                                disabled={twoFactorCode.length !== 6 || twoFactorActionLoading}
                                                onClick={handleConfirmTwoFactor}
                                                className="flex items-center gap-2 px-5 py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-red-500/25 disabled:opacity-50"
                                            >
                                                {twoFactorActionLoading ? (
                                                    <div className="w-4 h-4 border border-white border-t-transparent rounded-full animate-spin"></div>
                                                ) : (
                                                    <LuShieldCheck />
                                                )}
                                                Confirm &amp; Enable
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => { setTwoFactorSetup(null); setTwoFactorCode(''); }}
                                    className="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                                >
                                    Cancel setup
                                </button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div className="flex items-center gap-3 p-4 rounded-xl bg-gray-900/10 border border-gray-800/40">
                                    <LuShield className="text-gray-500 text-lg" />
                                    <span className="text-sm text-gray-400">Two-factor authentication is not enabled for your admin account.</span>
                                </div>

                                <button
                                    type="button"
                                    disabled={twoFactorActionLoading}
                                    onClick={handleSetupTwoFactor}
                                    className="flex items-center gap-2 px-5 py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-red-500/25 disabled:opacity-50"
                                >
                                    {twoFactorActionLoading ? (
                                        <div className="w-4 h-4 border border-white border-t-transparent rounded-full animate-spin"></div>
                                    ) : (
                                        <LuShield />
                                    )}
                                    Enable Two-Factor Authentication
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Submit Button */}
                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={saving}
                            className="flex items-center gap-2 px-6 py-3 bg-red-500 hover:bg-red-600 active:scale-95 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-red-500/25 disabled:opacity-50"
                        >
                            {saving ? (
                                <>
                                    <div className="w-4 h-4 border border-white border-t-transparent rounded-full animate-spin"></div>
                                    Saving changes...
                                </>
                            ) : (
                                <>
                                    <LuSave className="text-base" />
                                    Save Configurations
                                </>
                            )}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

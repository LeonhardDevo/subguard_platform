'use client';

import { useState, useEffect } from 'react';
import { api, AdminUser } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { 
    LuSearch, LuLock, LuLockOpen, LuSlidersHorizontal, LuEye, 
    LuBuilding, LuMail, LuUsers, LuClock, LuTriangleAlert, LuCircleCheck,
    LuUserPlus, LuPackagePlus, LuCreditCard, LuPlus, LuDollarSign, LuArrowRight, LuCalendar
} from 'react-icons/lu';

export default function TenantManagement() {
    const [users, setUsers] = useState<AdminUser[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalUsers, setTotalUsers] = useState(0);

    // Selected user for modals
    const [selectedUser, setSelectedUser] = useState<AdminUser | any | null>(null);
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);
    const [detailsLoading, setDetailsLoading] = useState(false);
    const [activeTab, setActiveTab] = useState<'overview' | 'customer' | 'plan' | 'subscription' | 'payment'>('overview');

    // Customer Form State
    const [custName, setCustName] = useState('');
    const [custEmail, setCustEmail] = useState('');
    const [custPhone, setCustPhone] = useState('');
    const [custExternalId, setCustExternalId] = useState('');
    const [custLoading, setCustLoading] = useState(false);

    // Plan Form State
    const [planName, setPlanName] = useState('');
    const [planPrice, setPlanPrice] = useState('');
    const [planCurrency, setPlanCurrency] = useState('USD');
    const [planInterval, setPlanInterval] = useState('monthly');
    const [planTrialDays, setPlanTrialDays] = useState('0');
    const [planDescription, setPlanDescription] = useState('');
    const [planLoading, setPlanLoading] = useState(false);

    // Subscription Form State
    const [subCustomerId, setSubCustomerId] = useState('');
    const [subPlanId, setSubPlanId] = useState('');
    const [subLoading, setSubLoading] = useState(false);

    // Payment Form State
    const [paySubscriptionId, setPaySubscriptionId] = useState('');
    const [payAmount, setPayAmount] = useState('');
    const [payCurrency, setPayCurrency] = useState('USD');
    const [payLoading, setPayLoading] = useState(false);
    
    // Limit Override Modal State
    const [isLimitOpen, setIsLimitOpen] = useState(false);
    const [newLimit, setNewLimit] = useState<number>(50);
    const [limitSubmitLoading, setLimitSubmitLoading] = useState(false);

    // Action execution state
    const [runningActionUserId, setRunningActionUserId] = useState<number | null>(null);
    const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const fetchUsers = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {
                page: page.toString(),
                per_page: '15'
            };
            if (search.trim()) {
                params.search = search;
            }
            const res = await api.getAdminUsers(params);
            setUsers(res.data);
            setTotalPages(res.last_page || 1);
            setTotalUsers(res.total || res.data.length);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to fetch business owners.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchUsers();
    }, [page]);

    // Handle search submission
    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setPage(1);
        fetchUsers();
    };

    // Toggle Lock / Unlock
    const handleToggleLock = async (user: AdminUser) => {
        setRunningActionUserId(user.id);
        setToast(null);
        try {
            const res = await api.toggleAdminUserLock(user.id);
            setToast({
                type: 'success',
                message: res.message || `${user.name}'s account status has been updated.`
            });
            // Update user status locally
            setUsers(users.map(u => u.id === user.id ? { ...u, is_locked: !u.is_locked } : u));
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to update account lock status.' });
        } finally {
            setRunningActionUserId(null);
        }
    };

    // Open limit adjustment modal
    const openLimitModal = (user: AdminUser) => {
        setSelectedUser(user);
        setNewLimit(user.subscriber_limit);
        setIsLimitOpen(true);
    };

    // Submit limit override
    const handleSubmitLimit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        setLimitSubmitLoading(true);
        setToast(null);
        try {
            const res = await api.updateAdminUserLimit(selectedUser.id, newLimit);
            setToast({
                type: 'success',
                message: `Subscriber limit updated to ${newLimit} for ${selectedUser.name}.`
            });
            // Update limit locally
            setUsers(users.map(u => u.id === selectedUser.id ? { ...u, subscriber_limit: newLimit } : u));
            setIsLimitOpen(false);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to update subscriber limit.' });
        } finally {
            setLimitSubmitLoading(false);
        }
    };

    // Open User Profile details modal
    const openDetailsModal = async (user: AdminUser) => {
        setSelectedUser(user);
        setIsDetailsOpen(true);
        setDetailsLoading(true);
        setActiveTab('overview');
        try {
            const res = await api.getAdminUser(user.id);
            if (res.data) {
                setSelectedUser(res.data);
            }
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to fetch detailed tenant profile.' });
        } finally {
            setDetailsLoading(false);
        }
    };

    const refreshUserDetails = async (userId: number) => {
        try {
            const res = await api.getAdminUser(userId);
            if (res.data) {
                setSelectedUser(res.data);
                setUsers(prev => prev.map(u => u.id === userId ? {
                    ...u,
                    customers_count: res.data.customers_count,
                    plans_count: res.data.plans_count,
                    active_subscriptions_count: res.data.active_subscriptions_count,
                    total_subscriptions_count: res.data.total_subscriptions_count,
                } : u));
            }
        } catch (err) {
            console.error('Failed to refresh user details', err);
        }
    };

    const handleCreateCustomer = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        setCustLoading(true);
        try {
            await api.createTenantCustomer(selectedUser.id, {
                name: custName,
                email: custEmail,
                phone: custPhone || undefined,
                external_id: custExternalId || undefined,
                status: 'active'
            });
            setToast({ type: 'success', message: `Customer '${custName}' created successfully.` });
            await refreshUserDetails(selectedUser.id);
            setCustName('');
            setCustEmail('');
            setCustPhone('');
            setCustExternalId('');
            setActiveTab('overview');
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to create customer.' });
        } finally {
            setCustLoading(false);
        }
    };

    const handleCreatePlan = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        setPlanLoading(true);
        try {
            await api.createTenantPlan(selectedUser.id, {
                name: planName,
                price: planPrice,
                currency: planCurrency,
                interval: planInterval,
                trial_days: parseInt(planTrialDays) || 0,
                description: planDescription || undefined
            });
            setToast({ type: 'success', message: `Billing plan '${planName}' created successfully.` });
            await refreshUserDetails(selectedUser.id);
            setPlanName('');
            setPlanPrice('');
            setPlanCurrency('USD');
            setPlanInterval('monthly');
            setPlanTrialDays('0');
            setPlanDescription('');
            setActiveTab('overview');
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to create plan.' });
        } finally {
            setPlanLoading(false);
        }
    };

    const handleCreateSubscription = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        if (!subCustomerId || !subPlanId) {
            setToast({ type: 'error', message: 'Please select both a customer and a plan.' });
            return;
        }
        setSubLoading(true);
        try {
            await api.createTenantSubscription(selectedUser.id, {
                customer_id: parseInt(subCustomerId),
                plan_id: parseInt(subPlanId)
            });
            setToast({ type: 'success', message: 'Subscription started successfully.' });
            await refreshUserDetails(selectedUser.id);
            setSubCustomerId('');
            setSubPlanId('');
            setActiveTab('overview');
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to create subscription.' });
        } finally {
            setSubLoading(false);
        }
    };

    const handleCreatePayment = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        if (!paySubscriptionId) {
            setToast({ type: 'error', message: 'Please select a subscription.' });
            return;
        }
        setPayLoading(true);
        try {
            await api.createTenantManualPayment(parseInt(paySubscriptionId), {
                amount: parseFloat(payAmount),
                currency: payCurrency
            });
            setToast({ type: 'success', message: `Manual payment of $${payAmount} recorded successfully.` });
            await refreshUserDetails(selectedUser.id);
            setPaySubscriptionId('');
            setPayAmount('');
            setPayCurrency('USD');
            setActiveTab('overview');
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to record manual payment.' });
        } finally {
            setPayLoading(false);
        }
    };

    const closeDetailsModal = () => {
        setIsDetailsOpen(false);
        setSelectedUser(null);
        setActiveTab('overview');
        // Reset forms
        setCustName('');
        setCustEmail('');
        setCustPhone('');
        setCustExternalId('');
        setPlanName('');
        setPlanPrice('');
        setPlanCurrency('USD');
        setPlanInterval('monthly');
        setPlanTrialDays('0');
        setPlanDescription('');
        setSubCustomerId('');
        setSubPlanId('');
        setPaySubscriptionId('');
        setPayAmount('');
        setPayCurrency('USD');
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-white">Tenant Management</h1>
                    <p className="text-gray-400 mt-1">Manage business owner accounts, override subscription limits, and restrict access</p>
                </div>
                <div className="text-xs px-3 py-1 bg-gray-900/60 border border-gray-800 rounded-lg text-gray-400">
                    Total: <span className="font-semibold text-white">{totalUsers}</span> accounts
                </div>
            </div>

            {/* Notification Toast */}
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

            {/* Filters Bar */}
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-center bg-gray-900/20 border border-gray-800 p-4 rounded-2xl">
                <form onSubmit={handleSearchSubmit} className="relative w-full sm:max-w-md">
                    <LuSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 text-lg" />
                    <input
                        type="text"
                        placeholder="Search by name, email, or company..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full pl-10 pr-20 py-2.5 bg-gray-950/40 border border-gray-800/80 rounded-xl text-sm focus:outline-none focus:border-red-500/40 focus:ring-1 focus:ring-red-500/10 placeholder-gray-500 text-white transition-all"
                    />
                    <button
                        type="submit"
                        className="absolute right-2 top-1.5 bottom-1.5 px-3 bg-gray-800 hover:bg-gray-700 text-xs font-semibold text-gray-300 rounded-lg transition-colors"
                    >
                        Search
                    </button>
                </form>
            </div>

            {/* Tenants Table */}
            <div className="glass rounded-2xl overflow-hidden border border-gray-800">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-800 bg-gray-900/10 text-left">
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Business Owner</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Company</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4 text-center">Active Subs</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4 text-center">Limit</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Status</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Joined Date</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                [1, 2, 3, 4, 5].map(i => (
                                    <tr key={i}>
                                        <td colSpan={7} className="px-6 py-4">
                                            <div className="skeleton h-6 w-full"></div>
                                        </td>
                                    </tr>
                                ))
                            ) : users.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-12 text-center text-gray-500">
                                        <div className="flex flex-col items-center justify-center p-6 text-gray-400">
                                            <LuUsers className="text-3xl text-gray-600 mb-2" />
                                            <p className="font-semibold text-white">No tenants found</p>
                                            <p className="text-xs text-gray-500 mt-1">Try tweaking your search terms or verify your filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                users.map((user) => (
                                    <tr key={user.id} className="border-b border-gray-800/40 hover:bg-gray-800/20 transition-colors">
                                        <td className="px-6 py-4">
                                            <div>
                                                <p className="text-sm font-semibold text-white">{user.name}</p>
                                                <p className="text-xs text-gray-400 font-mono mt-0.5">{user.email}</p>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-gray-300">
                                            {user.company_name || <span className="text-gray-500 italic">None</span>}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className="text-sm font-bold text-white">
                                                {user.active_subscriptions_count ?? 0}
                                            </span>
                                            <span className="text-xs text-gray-500"> / {user.customers_count ?? 0} clients</span>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`px-2 py-1 rounded text-xs font-mono font-bold ${
                                                user.subscriber_limit > 50 
                                                    ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' 
                                                    : 'bg-gray-800 text-gray-400'
                                            }`}>
                                                {user.subscriber_limit}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            {user.is_locked ? (
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/15 text-red-400 border border-red-500/20">
                                                    <LuLock className="text-[10px]" /> Locked
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">
                                                    <LuLockOpen className="text-[10px]" /> Active
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-xs text-gray-400 font-medium">
                                            {formatDate(user.created_at)}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => openDetailsModal(user)}
                                                    className="p-1.5 hover:bg-gray-800 text-gray-400 hover:text-white rounded-lg border border-transparent hover:border-gray-700 transition-all"
                                                    title="View Profile Details"
                                                >
                                                    <LuEye className="text-sm" />
                                                </button>
                                                <button
                                                    onClick={() => openLimitModal(user)}
                                                    className="p-1.5 hover:bg-gray-800 text-gray-400 hover:text-white rounded-lg border border-transparent hover:border-gray-700 transition-all"
                                                    title="Adjust Subscriber Limit"
                                                >
                                                    <LuSlidersHorizontal className="text-sm" />
                                                </button>
                                                <button
                                                    disabled={runningActionUserId !== null}
                                                    onClick={() => handleToggleLock(user)}
                                                    className={`p-1.5 rounded-lg border transition-all ${
                                                        user.is_locked
                                                            ? 'bg-emerald-500/10 hover:bg-emerald-500 hover:text-white text-emerald-400 border-emerald-500/20'
                                                            : 'bg-red-500/10 hover:bg-red-500 hover:text-white text-red-400 border-red-500/20'
                                                    } disabled:opacity-50`}
                                                    title={user.is_locked ? "Unlock Account" : "Lock Account"}
                                                >
                                                    {runningActionUserId === user.id ? (
                                                        <div className="w-3.5 h-3.5 border border-current border-t-transparent rounded-full animate-spin"></div>
                                                    ) : user.is_locked ? (
                                                        <LuLockOpen className="text-sm" />
                                                    ) : (
                                                        <LuLock className="text-sm" />
                                                    )}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination footer */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between border-t border-gray-800 px-6 py-4 bg-gray-900/5">
                        <span className="text-xs text-gray-500">
                            Page <span className="font-semibold text-white">{page}</span> of <span className="font-semibold text-white">{totalPages}</span>
                        </span>
                        <div className="flex gap-2">
                            <button
                                disabled={page === 1 || loading}
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                                className="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 active:scale-95 disabled:opacity-40 disabled:active:scale-100 text-xs font-semibold text-gray-300 rounded-lg transition-all"
                            >
                                Previous
                            </button>
                            <button
                                disabled={page === totalPages || loading}
                                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                                className="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 active:scale-95 disabled:opacity-40 disabled:active:scale-100 text-xs font-semibold text-gray-300 rounded-lg transition-all"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Modal: Adjust Subscriber Limit */}
            {isLimitOpen && selectedUser && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setIsLimitOpen(false)}></div>
                    <div className="relative w-full max-w-md glass border border-red-500/10 rounded-2xl p-6 shadow-2xl animate-fade-in text-white space-y-6">
                        <div className="flex items-center justify-between border-b border-gray-800 pb-3">
                            <h3 className="text-lg font-bold flex items-center gap-2">
                                <LuSlidersHorizontal className="text-red-400" />
                                Adjust Subscriber Limit
                            </h3>
                            <button onClick={() => setIsLimitOpen(false)} className="text-gray-400 hover:text-white uppercase font-bold text-xs">Close</button>
                        </div>

                        <form onSubmit={handleSubmitLimit} className="space-y-4">
                            <div>
                                <p className="text-xs text-gray-400">User Account</p>
                                <p className="text-sm font-semibold mt-0.5">{selectedUser.name} ({selectedUser.company_name})</p>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs text-gray-400 font-medium block">Active Subscriber Limit</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    value={newLimit}
                                    onChange={(e) => setNewLimit(parseInt(e.target.value) || 0)}
                                    className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-red-500/40 text-white font-mono"
                                    required
                                />
                                <span className="text-[11px] text-gray-500 block">
                                    The default system limit for free business owners is <span className="font-semibold text-gray-400">50</span> active subscribers.
                                </span>
                            </div>

                            <button
                                type="submit"
                                disabled={limitSubmitLoading}
                                className="w-full py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-sm font-bold text-white rounded-xl transition-all shadow-lg shadow-red-500/20 disabled:opacity-50"
                            >
                                {limitSubmitLoading ? 'Saving changes...' : 'Save Limit Override'}
                            </button>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: View Details Profile & Resource Management Drawer */}
            {isDetailsOpen && selectedUser && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={closeDetailsModal}></div>
                    <div className="relative w-full max-w-2xl glass border border-gray-800 rounded-2xl p-6 shadow-2xl animate-fade-in text-white space-y-5 flex flex-col max-h-[90vh]">
                        {/* Header */}
                        <div className="flex items-center justify-between border-b border-gray-800 pb-3 flex-shrink-0">
                            <h3 className="text-lg font-bold flex items-center gap-2">
                                <LuEye className="text-red-400" />
                                Tenant Resource Override
                            </h3>
                            <button onClick={closeDetailsModal} className="text-gray-400 hover:text-white uppercase font-bold text-xs">Close</button>
                        </div>

                        {/* Profile Summary Header (Non-scrollable) */}
                        <div className="flex items-center gap-4 flex-shrink-0">
                            <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-pink-500 flex items-center justify-center font-bold text-lg text-white">
                                {selectedUser.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 className="font-bold text-base leading-snug">{selectedUser.name}</h4>
                                <p className="text-xs text-gray-400 font-mono mt-0.5">{selectedUser.email}</p>
                            </div>
                            <span className={`ml-auto px-2.5 py-0.5 rounded-full text-xs font-semibold border ${
                                selectedUser.is_locked
                                    ? 'bg-red-500/15 text-red-400 border-red-500/30'
                                    : 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                            }`}>
                                {selectedUser.is_locked ? 'Locked' : 'Active'}
                            </span>
                        </div>

                        {/* Navigation Tabs */}
                        <div className="flex gap-1.5 border-b border-gray-800 overflow-x-auto pb-2 flex-shrink-0 scrollbar-none">
                            {[
                                { id: 'overview', label: 'Overview' },
                                { id: 'customer', label: '+ Customer' },
                                { id: 'plan', label: '+ Plan' },
                                { id: 'subscription', label: '+ Subscription' },
                                { id: 'payment', label: '+ Payment' },
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id as any)}
                                    className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap ${
                                        activeTab === tab.id 
                                            ? 'bg-red-500 text-white shadow-md shadow-red-500/20' 
                                            : 'text-gray-400 hover:text-white bg-gray-900/40 hover:bg-gray-800/40'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {/* Scrollable Content Pane */}
                        <div className="flex-1 overflow-y-auto min-h-0 space-y-4 pr-1">
                            {detailsLoading ? (
                                <div className="py-12 flex flex-col items-center justify-center text-center">
                                    <div className="w-8 h-8 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></div>
                                    <p className="text-xs text-gray-400 mt-3">Loading tenant assets and relationships...</p>
                                </div>
                            ) : (
                                <>
                                    {/* Tab: OVERVIEW */}
                                    {activeTab === 'overview' && (
                                        <div className="space-y-4 animate-fade-in">
                                            {/* Info Fields Grid */}
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="p-3 bg-gray-900/40 border border-gray-800/40 rounded-xl">
                                                    <span className="text-[11px] text-gray-500 uppercase tracking-wider flex items-center gap-1"><LuBuilding /> Company</span>
                                                    <p className="text-sm font-semibold text-gray-300 mt-1">{selectedUser.company_name || 'Not provided'}</p>
                                                </div>
                                                <div className="p-3 bg-gray-900/40 border border-gray-800/40 rounded-xl">
                                                    <span className="text-[11px] text-gray-500 uppercase tracking-wider flex items-center gap-1"><LuClock /> Joined Date</span>
                                                    <p className="text-sm font-semibold text-gray-300 mt-1">{formatDate(selectedUser.created_at)}</p>
                                                </div>
                                            </div>

                                            {/* Counters / Stats */}
                                            <div className="grid grid-cols-3 gap-3">
                                                <div className="p-3.5 bg-gray-900/20 border border-gray-800/60 rounded-xl text-center">
                                                    <p className="text-lg font-bold text-white">{selectedUser.customers_count ?? 0}</p>
                                                    <p className="text-[10px] text-gray-500 mt-0.5">Customers</p>
                                                </div>
                                                <div className="p-3.5 bg-gray-900/20 border border-gray-800/60 rounded-xl text-center">
                                                    <p className="text-lg font-bold text-white">{selectedUser.plans_count ?? 0}</p>
                                                    <p className="text-[10px] text-gray-500 mt-0.5">Plans</p>
                                                </div>
                                                <div className="p-3.5 bg-gray-900/20 border border-gray-800/60 rounded-xl text-center">
                                                    <p className="text-lg font-bold text-emerald-400">{selectedUser.active_subscriptions_count ?? 0}</p>
                                                    <p className="text-[10px] text-gray-500 mt-0.5">Active Subs</p>
                                                </div>
                                            </div>

                                            {/* Active Subscriptions List */}
                                            <div className="space-y-2">
                                                <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-1.5">
                                                    <LuCreditCard className="text-gray-500" /> Active Subscriptions ({selectedUser.subscriptions?.length || 0})
                                                </h5>
                                                {selectedUser.subscriptions && selectedUser.subscriptions.length > 0 ? (
                                                    <div className="space-y-2 max-h-48 overflow-y-auto bg-black/20 p-2.5 rounded-xl border border-gray-800/50">
                                                        {selectedUser.subscriptions.map((sub: any) => (
                                                            <div key={sub.id} className="flex justify-between items-center text-xs p-2.5 bg-gray-900/40 border border-gray-800/50 rounded-lg hover:border-gray-700/50 transition-all">
                                                                <div>
                                                                    <p className="font-semibold text-gray-200">{sub.customer?.name || 'Unknown Client'}</p>
                                                                    <p className="text-[10px] text-gray-500 font-mono mt-0.5">
                                                                        {sub.plan?.name} &bull; ${sub.plan?.price} {sub.plan?.currency} / {sub.plan?.interval}
                                                                    </p>
                                                                </div>
                                                                <div className="text-right">
                                                                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                                        sub.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' :
                                                                        sub.status === 'trialing' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/20' :
                                                                        'bg-gray-850 text-gray-400'
                                                                    }`}>{sub.status}</span>
                                                                    <p className="text-[9px] text-gray-500 mt-1 font-mono">Next: {formatDate(sub.next_billing_date)}</p>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <div className="p-6 bg-gray-900/10 border border-dashed border-gray-800 rounded-xl text-center text-xs text-gray-500 italic">
                                                        No active subscriptions recorded.
                                                    </div>
                                                )}
                                            </div>

                                            {/* System Override Summary Box */}
                                            <div className="p-4 bg-red-950/10 border border-red-500/20 rounded-xl flex justify-between items-center">
                                                <div className="space-y-0.5">
                                                    <h5 className="text-xs font-bold text-red-400 uppercase tracking-wider flex items-center gap-1.5">
                                                        <LuSlidersHorizontal /> Platform Overrides
                                                    </h5>
                                                    <p className="text-[11px] text-gray-500">Active subscriber limit allowed for this tenant</p>
                                                </div>
                                                <span className="font-mono font-bold text-sm bg-red-500/10 text-red-400 border border-red-500/25 px-3 py-1 rounded-lg">
                                                    {selectedUser.subscriber_limit} Limit
                                                </span>
                                            </div>
                                        </div>
                                    )}

                                    {/* Tab: CREATE CUSTOMER */}
                                    {activeTab === 'customer' && (
                                        <form onSubmit={handleCreateCustomer} className="space-y-4 animate-fade-in">
                                            <div className="space-y-1">
                                                <h4 className="text-sm font-semibold text-white flex items-center gap-1.5"><LuUserPlus /> Add New Customer</h4>
                                                <p className="text-[11px] text-gray-400">Registers a customer directly into this business owner's account.</p>
                                            </div>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Customer Full Name</label>
                                                    <input
                                                        type="text"
                                                        placeholder="e.g. John Doe"
                                                        value={custName}
                                                        onChange={(e) => setCustName(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Email Address</label>
                                                    <input
                                                        type="email"
                                                        placeholder="e.g. john@example.com"
                                                        value={custEmail}
                                                        onChange={(e) => setCustEmail(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Phone Number (Optional)</label>
                                                    <input
                                                        type="tel"
                                                        placeholder="e.g. +1 555-0199"
                                                        value={custPhone}
                                                        onChange={(e) => setCustPhone(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white"
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">External CRM/Stripe ID (Optional)</label>
                                                    <input
                                                        type="text"
                                                        placeholder="e.g. cus_H12x3..."
                                                        value={custExternalId}
                                                        onChange={(e) => setCustExternalId(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white font-mono"
                                                    />
                                                </div>
                                            </div>
                                            <button
                                                type="submit"
                                                disabled={custLoading}
                                                className="w-full py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-xs font-bold text-white rounded-xl transition-all shadow-md shadow-red-500/20 disabled:opacity-50"
                                            >
                                                {custLoading ? 'Creating customer...' : 'Register Customer'}
                                            </button>
                                        </form>
                                    )}

                                    {/* Tab: CREATE PLAN */}
                                    {activeTab === 'plan' && (
                                        <form onSubmit={handleCreatePlan} className="space-y-4 animate-fade-in">
                                            <div className="space-y-1">
                                                <h4 className="text-sm font-semibold text-white flex items-center gap-1.5"><LuPackagePlus /> Create Billing Plan</h4>
                                                <p className="text-[11px] text-gray-400">Defines a pricing tier for this tenant's clients.</p>
                                            </div>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Plan Name</label>
                                                    <input
                                                        type="text"
                                                        placeholder="e.g. Pro Monthly"
                                                        value={planName}
                                                        onChange={(e) => setPlanName(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Price Amount</label>
                                                    <div className="relative">
                                                        <span className="absolute left-3 top-2 text-xs text-gray-500 font-semibold">$</span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            placeholder="0.00"
                                                            value={planPrice}
                                                            onChange={(e) => setPlanPrice(e.target.value)}
                                                            className="w-full bg-gray-950/60 border border-gray-800 rounded-xl pl-6 pr-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white font-mono"
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Currency</label>
                                                    <select
                                                        value={planCurrency}
                                                        onChange={(e) => setPlanCurrency(e.target.value)}
                                                        className="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-gray-300"
                                                    >
                                                        <option value="USD">USD ($)</option>
                                                        <option value="EUR">EUR (€)</option>
                                                        <option value="GBP">GBP (£)</option>
                                                    </select>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Billing Interval</label>
                                                    <select
                                                        value={planInterval}
                                                        onChange={(e) => setPlanInterval(e.target.value)}
                                                        className="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-gray-300"
                                                    >
                                                        <option value="weekly">Weekly</option>
                                                        <option value="monthly">Monthly</option>
                                                        <option value="yearly">Yearly</option>
                                                    </select>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-gray-400 font-medium">Trial Period (Days)</label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={planTrialDays}
                                                        onChange={(e) => setPlanTrialDays(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white font-mono"
                                                    />
                                                </div>
                                                <div className="space-y-1.5 sm:col-span-2">
                                                    <label className="text-xs text-gray-400 font-medium">Plan Description (Optional)</label>
                                                    <textarea
                                                        rows={2}
                                                        placeholder="Describe plan features..."
                                                        value={planDescription}
                                                        onChange={(e) => setPlanDescription(e.target.value)}
                                                        className="w-full bg-gray-950/60 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white resize-none"
                                                    />
                                                </div>
                                            </div>
                                            <button
                                                type="submit"
                                                disabled={planLoading}
                                                className="w-full py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-xs font-bold text-white rounded-xl transition-all shadow-md shadow-red-500/20 disabled:opacity-50"
                                            >
                                                {planLoading ? 'Creating plan...' : 'Create Billing Plan'}
                                            </button>
                                        </form>
                                    )}

                                    {/* Tab: START SUBSCRIPTION */}
                                    {activeTab === 'subscription' && (
                                        <form onSubmit={handleCreateSubscription} className="space-y-4 animate-fade-in">
                                            <div className="space-y-1">
                                                <h4 className="text-sm font-semibold text-white flex items-center gap-1.5"><LuCreditCard /> Create Subscription</h4>
                                                <p className="text-[11px] text-gray-400">Manually starts a subscription plan for a client.</p>
                                            </div>

                                            {(!selectedUser.customers || selectedUser.customers.length === 0) ? (
                                                <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-400 flex items-start gap-2.5">
                                                    <LuTriangleAlert className="text-lg flex-shrink-0 mt-0.5" />
                                                    <div>
                                                        <p className="font-semibold">No customers registered</p>
                                                        <p className="mt-0.5 text-gray-400">Please go to the <span className="font-semibold text-white underline cursor-pointer" onClick={() => setActiveTab('customer')}>+ Customer</span> tab to create a customer first.</p>
                                                    </div>
                                                </div>
                                            ) : (!selectedUser.plans || selectedUser.plans.length === 0) ? (
                                                <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-400 flex items-start gap-2.5">
                                                    <LuTriangleAlert className="text-lg flex-shrink-0 mt-0.5" />
                                                    <div>
                                                        <p className="font-semibold">No billing plans configured</p>
                                                        <p className="mt-0.5 text-gray-400">Please go to the <span className="font-semibold text-white underline cursor-pointer" onClick={() => setActiveTab('plan')}>+ Plan</span> tab to create a plan first.</p>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="space-y-4">
                                                    <div className="space-y-1.5">
                                                        <label className="text-xs text-gray-400 font-medium">Select Customer</label>
                                                        <select
                                                            value={subCustomerId}
                                                            onChange={(e) => setSubCustomerId(e.target.value)}
                                                            className="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:border-red-500/40 text-gray-300 font-medium"
                                                            required
                                                        >
                                                            <option value="">-- Choose Customer --</option>
                                                            {selectedUser.customers.map((cust: any) => (
                                                                <option key={cust.id} value={cust.id}>{cust.name} ({cust.email})</option>
                                                            ))}
                                                        </select>
                                                    </div>

                                                    <div className="space-y-1.5">
                                                        <label className="text-xs text-gray-400 font-medium">Select Billing Plan</label>
                                                        <select
                                                            value={subPlanId}
                                                            onChange={(e) => setSubPlanId(e.target.value)}
                                                            className="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:border-red-500/40 text-gray-300 font-medium"
                                                            required
                                                        >
                                                            <option value="">-- Choose Plan --</option>
                                                            {selectedUser.plans.map((pl: any) => (
                                                                <option key={pl.id} value={pl.id}>{pl.name} (${pl.price} / {pl.interval})</option>
                                                            ))}
                                                        </select>
                                                    </div>

                                                    <button
                                                        type="submit"
                                                        disabled={subLoading}
                                                        className="w-full py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-xs font-bold text-white rounded-xl transition-all shadow-md shadow-red-500/20 disabled:opacity-50"
                                                    >
                                                        {subLoading ? 'Creating subscription...' : 'Start Manual Subscription'}
                                                    </button>
                                                </div>
                                            )}
                                        </form>
                                    )}

                                    {/* Tab: RECORD PAYMENT */}
                                    {activeTab === 'payment' && (
                                        <form onSubmit={handleCreatePayment} className="space-y-4 animate-fade-in">
                                            <div className="space-y-1">
                                                <h4 className="text-sm font-semibold text-white flex items-center gap-1.5"><LuDollarSign /> Record Manual Payment</h4>
                                                <p className="text-[11px] text-gray-400">Logs a manual payment to extend the subscription period.</p>
                                            </div>

                                            {(!selectedUser.subscriptions || selectedUser.subscriptions.length === 0) ? (
                                                <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-400 flex items-start gap-2.5">
                                                    <LuTriangleAlert className="text-lg flex-shrink-0 mt-0.5" />
                                                    <div>
                                                        <p className="font-semibold">No subscriptions active</p>
                                                        <p className="mt-0.5 text-gray-400">There are no client subscriptions to record payments against on this account.</p>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="space-y-4">
                                                    <div className="space-y-1.5">
                                                        <label className="text-xs text-gray-400 font-medium">Select Subscription</label>
                                                        <select
                                                            value={paySubscriptionId}
                                                            onChange={(e) => setPaySubscriptionId(e.target.value)}
                                                            className="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:border-red-500/40 text-gray-300 font-medium"
                                                            required
                                                        >
                                                            <option value="">-- Choose Subscription --</option>
                                                            {selectedUser.subscriptions.map((sub: any) => (
                                                                <option key={sub.id} value={sub.id}>
                                                                    {sub.customer?.name} &bull; {sub.plan?.name} (${sub.plan?.price}/{sub.plan?.interval})
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>

                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div className="space-y-1.5">
                                                            <label className="text-xs text-gray-400 font-medium">Payment Amount</label>
                                                            <div className="relative">
                                                                <span className="absolute left-3 top-2 text-xs text-gray-500 font-semibold">$</span>
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    placeholder="0.00"
                                                                    value={payAmount}
                                                                    onChange={(e) => setPayAmount(e.target.value)}
                                                                    className="w-full bg-gray-950/60 border border-gray-800 rounded-xl pl-6 pr-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-white font-mono"
                                                                    required
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="space-y-1.5">
                                                            <label className="text-xs text-gray-400 font-medium">Currency</label>
                                                            <select
                                                                value={payCurrency}
                                                                onChange={(e) => setPayCurrency(e.target.value)}
                                                                className="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-red-500/40 text-gray-300"
                                                            >
                                                                <option value="USD">USD ($)</option>
                                                                <option value="EUR">EUR (€)</option>
                                                                <option value="GBP">GBP (£)</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="submit"
                                                        disabled={payLoading}
                                                        className="w-full py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-xs font-bold text-white rounded-xl transition-all shadow-md shadow-red-500/20 disabled:opacity-50"
                                                    >
                                                        {payLoading ? 'Recording manual payment...' : 'Record Manual Payment'}
                                                    </button>
                                                </div>
                                            )}
                                        </form>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Footer (Non-scrollable) */}
                        <div className="flex gap-3 justify-end pt-3 border-t border-gray-800 flex-shrink-0">
                            {activeTab === 'overview' && (
                                <button
                                    onClick={() => {
                                        setIsDetailsOpen(false);
                                        openLimitModal(selectedUser);
                                    }}
                                    className="px-4 py-2 bg-gray-850 hover:bg-gray-800 text-xs font-semibold text-gray-300 border border-gray-800 hover:border-gray-700 rounded-xl transition-all"
                                >
                                    Modify Subscriber Limit
                                </button>
                            )}
                            <button
                                onClick={closeDetailsModal}
                                className="px-4 py-2 bg-red-500 hover:bg-red-600 text-xs font-bold text-white rounded-xl transition-all shadow-md shadow-red-500/10"
                            >
                                Close Details
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

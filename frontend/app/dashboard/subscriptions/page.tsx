'use client';

import { useState, useEffect } from 'react';
import { api, Subscription, Customer, Plan } from '@/lib/api';
import GlassSelect from '@/components/GlassSelect';
import { formatDate } from '@/lib/format';

export default function SubscriptionsPage() {
    const [subscriptions, setSubscriptions] = useState<Subscription[]>([]);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [plans, setPlans] = useState<Plan[]>([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState<{ value: string; label: string } | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [selectedCustomer, setSelectedCustomer] = useState<{ value: string; label: string } | null>(null);
    const [selectedPlan, setSelectedPlan] = useState<{ value: string; label: string } | null>(null);
    const [formError, setFormError] = useState('');

    const loadData = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (statusFilter?.value) params.status = statusFilter.value;
            const [subsRes, custRes, plansRes] = await Promise.all([
                api.getSubscriptions(params),
                api.getCustomers({ per_page: '100' }),
                api.getPlans({ per_page: '100' }),
            ]);
            setSubscriptions(subsRes.data);
            setCustomers(custRes.data);
            setPlans(plansRes.data);
        } catch (e) { console.error(e); }
        setLoading(false);
    };

    useEffect(() => { loadData(); }, [statusFilter]);

    const handleCreate = async () => {
        setFormError('');
        try {
            await api.createSubscription({ customer_id: parseInt(selectedCustomer!.value), plan_id: parseInt(selectedPlan!.value) });
            setShowForm(false);
            setSelectedCustomer(null);
            setSelectedPlan(null);
            loadData();
        } catch (err: unknown) {
            setFormError(err instanceof Error ? err.message : 'Failed to create');
        }
    };

    const handleCancel = async (id: number) => {
        const reason = prompt('Cancel reason (optional):');
        if (reason === null) return;
        await api.cancelSubscription(id, reason || undefined);
        loadData();
    };

    const statusBadge = (status: string) => {
        const colors: Record<string, string> = {
            active: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            trialing: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30',
            past_due: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
            canceled: 'bg-red-500/15 text-red-400 border-red-500/30',
            paused: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
        };
        return `inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium border ${colors[status] || colors.active}`;
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-white">Subscriptions</h1>
                    <p className="text-gray-400 mt-1">Manage customer subscriptions</p>
                </div>
                <button onClick={() => setShowForm(true)}
                    className="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white font-medium rounded-xl hover:from-indigo-500 hover:to-cyan-500 transition-all">
                    + New Subscription
                </button>
            </div>

            {/* Filter */}
            <div className="w-full sm:w-64">
                <GlassSelect
                    value={statusFilter}
                    onChange={(val: any) => setStatusFilter(val)}
                    options={[
                        { value: '', label: 'All Status' },
                        { value: 'active', label: 'Active' },
                        { value: 'trialing', label: 'Trialing' },
                        { value: 'past_due', label: 'Past Due' },
                        { value: 'canceled', label: 'Canceled' },
                    ]}
                    placeholder="Filter Status..."
                />
            </div>

            {/* Create Modal */}
            {showForm && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50" onClick={() => setShowForm(false)}>
                    <div className="glass rounded-2xl p-8 w-full max-w-md" onClick={(e) => e.stopPropagation()}>
                        <h2 className="text-xl font-semibold text-white mb-6">New Subscription</h2>
                        {formError && <div className="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">{formError}</div>}
                        <div className="space-y-4">
                            <GlassSelect
                                value={selectedCustomer}
                                onChange={(val: any) => setSelectedCustomer(val)}
                                options={customers.map(c => ({ value: String(c.id), label: `${c.name} (${c.email})` }))}
                                placeholder="Search Customer..."
                                isSearchable={true}
                            />
                            <GlassSelect
                                value={selectedPlan}
                                onChange={(val: any) => setSelectedPlan(val)}
                                options={plans.map(p => ({ value: String(p.id), label: `${p.name} — $${parseFloat(p.price).toFixed(2)}/${p.interval}` }))}
                                placeholder="Search Plan..."
                                isSearchable={true}
                            />
                            <div className="flex gap-3">
                                <button onClick={() => setShowForm(false)} className="flex-1 py-3 bg-gray-800 text-gray-300 rounded-xl hover:bg-gray-700 transition-all">Cancel</button>
                                <button onClick={handleCreate} disabled={!selectedCustomer || !selectedPlan}
                                    className="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white rounded-xl hover:from-indigo-500 hover:to-cyan-500 transition-all disabled:opacity-50">
                                    Create
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Table */}
            <div className="glass rounded-2xl overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1000px]">
                        <thead>
                            <tr className="border-b border-gray-800">
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Customer</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Plan</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Status</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Started</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Next Billing</th>
                                <th className="text-right text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                [1, 2, 3].map(i => <tr key={i}><td colSpan={6} className="px-6 py-4"><div className="skeleton h-6 w-full"></div></td></tr>)
                            ) : subscriptions.length === 0 ? (
                                <tr><td colSpan={6} className="px-6 py-12 text-center text-gray-500">No subscriptions found</td></tr>
                            ) : (
                                subscriptions.map((sub) => (
                                    <tr key={sub.id} className="border-b border-gray-800/50 hover:bg-gray-800/30 transition-colors">
                                        <td className="px-6 py-4 text-white font-medium">{sub.customer?.name || `#${sub.customer_id}`}</td>
                                        <td className="px-6 py-4 text-gray-400">{sub.plan?.name || `#${sub.plan_id}`}</td>
                                        <td className="px-6 py-4"><span className={statusBadge(sub.status)}>{sub.status}</span></td>
                                        <td className="px-6 py-4 text-gray-400">{formatDate(sub.start_date)}</td>
                                        <td className="px-6 py-4 text-gray-400">{formatDate(sub.next_billing_date)}</td>
                                        <td className="px-6 py-4 text-right">
                                            {sub.status === 'active' || sub.status === 'trialing' ? (
                                                <button onClick={() => handleCancel(sub.id)} className="text-red-400 hover:text-red-300 text-sm font-medium transition-colors">Cancel</button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

'use client';

import { useState, useEffect, FormEvent } from 'react';
import { api, Plan } from '@/lib/api';
import GlassSelect from '@/components/GlassSelect';

export default function PlansPage() {
    const [plans, setPlans] = useState<Plan[]>([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingPlan, setEditingPlan] = useState<Plan | null>(null);
    const [form, setForm] = useState({ name: '', price: '', currency: 'USD', interval: { value: 'monthly', label: 'Monthly' }, trial_days: '0', description: '' });
    const [formError, setFormError] = useState('');

    const loadPlans = async () => {
        setLoading(true);
        try {
            const res = await api.getPlans();
            setPlans(res.data);
        } catch (e) { console.error(e); }
        setLoading(false);
    };

    useEffect(() => { loadPlans(); }, []);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setFormError('');
        try {
            const payload: any = { ...form, interval: form.interval.value, price: parseFloat(form.price), trial_days: parseInt(form.trial_days) };
            if (editingPlan) {
                await api.updatePlan(editingPlan.id, payload);
            } else {
                await api.createPlan(payload);
            }
            setShowForm(false);
            setEditingPlan(null);
            setForm({ name: '', price: '', currency: 'USD', interval: { value: 'monthly', label: 'Monthly' }, trial_days: '0', description: '' });
            loadPlans();
        } catch (err: unknown) {
            setFormError(err instanceof Error ? err.message : 'Failed to save');
        }
    };

    const handleEdit = (p: Plan) => {
        setEditingPlan(p);
        const intLabel = p.interval.charAt(0).toUpperCase() + p.interval.slice(1);
        setForm({ name: p.name, price: p.price, currency: p.currency, interval: { value: p.interval, label: intLabel }, trial_days: String(p.trial_days), description: p.description || '' });
        setShowForm(true);
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this plan?')) return;
        try {
            await api.deletePlan(id);
            loadPlans();
        } catch (err: unknown) {
            alert(err instanceof Error ? err.message : 'Cannot delete plan');
        }
    };

    const intervalLabel: Record<string, string> = { monthly: '/mo', yearly: '/yr', weekly: '/wk' };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-white">Plans</h1>
                    <p className="text-gray-400 mt-1">Manage subscription plans</p>
                </div>
                <button onClick={() => { setShowForm(true); setEditingPlan(null); setForm({ name: '', price: '', currency: 'USD', interval: { value: 'monthly', label: 'Monthly' }, trial_days: '0', description: '' }); }}
                    className="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white font-medium rounded-xl hover:from-indigo-500 hover:to-cyan-500 transition-all">
                    + New Plan
                </button>
            </div>

            {/* Form Modal */}
            {showForm && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50" onClick={() => setShowForm(false)}>
                    <div className="glass rounded-2xl p-8 w-full max-w-md" onClick={(e) => e.stopPropagation()}>
                        <h2 className="text-xl font-semibold text-white mb-6">{editingPlan ? 'Edit Plan' : 'New Plan'}</h2>
                        {formError && <div className="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">{formError}</div>}
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <input type="text" placeholder="Plan Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required
                                className="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <div className="flex flex-col sm:flex-row gap-3">
                                <input type="number" step="0.01" placeholder="Price" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} required
                                    className="w-full sm:flex-1 px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <div className="w-full sm:w-1/2">
                                    <GlassSelect
                                        value={form.interval}
                                        onChange={(val: any) => setForm({ ...form, interval: val })}
                                        options={[
                                            { value: 'monthly', label: 'Monthly' },
                                            { value: 'yearly', label: 'Yearly' },
                                            { value: 'weekly', label: 'Weekly' },
                                        ]}
                                        isSearchable={false}
                                    />
                                </div>
                            </div>
                            <input type="number" placeholder="Trial Days (0 = no trial)" value={form.trial_days} onChange={(e) => setForm({ ...form, trial_days: e.target.value })}
                                className="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <textarea placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3}
                                className="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                            <div className="flex gap-3">
                                <button type="button" onClick={() => setShowForm(false)} className="flex-1 py-3 bg-gray-800 text-gray-300 rounded-xl hover:bg-gray-700 transition-all">Cancel</button>
                                <button type="submit" className="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white rounded-xl hover:from-indigo-500 hover:to-cyan-500 transition-all">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Plan Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {loading ? (
                    [1, 2, 3].map(i => <div key={i} className="skeleton h-48 rounded-2xl"></div>)
                ) : plans.length === 0 ? (
                    <div className="col-span-full text-center py-12 text-gray-500">No plans created yet</div>
                ) : (
                    plans.map((plan) => (
                        <div key={plan.id} className="glass rounded-2xl p-6 card-hover">
                            <div className="flex justify-between items-start mb-4">
                                <div>
                                    <h3 className="text-lg font-semibold text-white">{plan.name}</h3>
                                    {plan.description && <p className="text-sm text-gray-400 mt-1">{plan.description}</p>}
                                </div>
                                <span className={`shrink-0 px-2 py-0.5 rounded-full text-xs font-medium ${plan.is_active ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-gray-500/15 text-gray-400 border border-gray-500/30'}`}>
                                    {plan.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <div className="mb-4">
                                <span className="text-3xl font-bold text-white">${parseFloat(plan.price).toFixed(2)}</span>
                                <span className="text-gray-400">{intervalLabel[plan.interval]}</span>
                            </div>
                            {plan.trial_days > 0 && (
                                <p className="text-sm text-indigo-400 mb-4">{plan.trial_days}-day free trial</p>
                            )}
                            <div className="flex gap-2">
                                <button onClick={() => handleEdit(plan)} className="flex-1 py-2 text-sm bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition-all">Edit</button>
                                <button onClick={() => handleDelete(plan.id)} className="px-4 py-2 text-sm text-red-400 bg-red-500/10 rounded-lg hover:bg-red-500/20 transition-all">Delete</button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

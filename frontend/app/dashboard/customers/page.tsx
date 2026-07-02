'use client';

import { useState, useEffect, FormEvent } from 'react';
import { api, Customer } from '@/lib/api';
import GlassSelect from '@/components/GlassSelect';

export default function CustomersPage() {
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<{ value: string; label: string } | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
    const [form, setForm] = useState({ name: '', email: '', phone: '', status: { value: 'active', label: 'Active' } });
    const [formError, setFormError] = useState('');

    const loadCustomers = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            if (statusFilter?.value) params.status = statusFilter.value;
            const res = await api.getCustomers(params);
            setCustomers(res.data);
        } catch (e) { console.error(e); }
        setLoading(false);
    };

    useEffect(() => { loadCustomers(); }, [search, statusFilter]);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setFormError('');
        try {
            const submitData = { ...form, status: form.status.value };
            if (editingCustomer) {
                await api.updateCustomer(editingCustomer.id, submitData);
            } else {
                await api.createCustomer(submitData);
            }
            setShowForm(false);
            setEditingCustomer(null);
            setForm({ name: '', email: '', phone: '', status: { value: 'active', label: 'Active' } });
            loadCustomers();
        } catch (err: unknown) {
            setFormError(err instanceof Error ? err.message : 'Failed to save');
        }
    };

    const handleEdit = (c: Customer) => {
        setEditingCustomer(c);
        setForm({ name: c.name, email: c.email, phone: c.phone || '', status: { value: c.status, label: c.status.charAt(0).toUpperCase() + c.status.slice(1) } });
        setShowForm(true);
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this customer?')) return;
        await api.deleteCustomer(id);
        loadCustomers();
    };

    const statusBadge = (status: string) => {
        const colors: Record<string, string> = {
            active: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            inactive: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
            blocked: 'bg-red-500/15 text-red-400 border-red-500/30',
        };
        return `inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium border ${colors[status] || colors.active}`;
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-white">Customers</h1>
                    <p className="text-gray-400 mt-1">Manage your customer base</p>
                </div>
                <button onClick={() => { setShowForm(true); setEditingCustomer(null); setForm({ name: '', email: '', phone: '', status: { value: 'active', label: 'Active' } }); }}
                    className="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white font-medium rounded-xl hover:from-indigo-500 hover:to-cyan-500 transition-all">
                    + Add Customer
                </button>
            </div>

            <div className="flex flex-col sm:flex-row gap-4">
                <input type="text" placeholder="Search customers..." value={search} onChange={(e) => setSearch(e.target.value)}
                    className="flex-1 px-4 py-2.5 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all" />
                <div className="w-full sm:w-48">
                    <GlassSelect
                        value={statusFilter}
                        onChange={(val: any) => setStatusFilter(val)}
                        options={[
                            { value: '', label: 'All Status' },
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                            { value: 'blocked', label: 'Blocked' },
                        ]}
                        placeholder="Filter Status..."
                    />
                </div>
            </div>

            {/* Form Modal */}
            {showForm && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50" onClick={() => setShowForm(false)}>
                    <div className="glass rounded-2xl p-8 w-full max-w-md" onClick={(e) => e.stopPropagation()}>
                        <h2 className="text-xl font-semibold text-white mb-6">{editingCustomer ? 'Edit Customer' : 'New Customer'}</h2>
                        {formError && <div className="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">{formError}</div>}
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <input type="text" placeholder="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required
                                className="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <input type="email" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required
                                className="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" placeholder="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                className="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <GlassSelect
                                value={form.status}
                                onChange={(val: any) => setForm({ ...form, status: val })}
                                options={[
                                    { value: 'active', label: 'Active' },
                                    { value: 'inactive', label: 'Inactive' },
                                    { value: 'blocked', label: 'Blocked' },
                                ]}
                            />
                            <div className="flex gap-3">
                                <button type="button" onClick={() => setShowForm(false)} className="flex-1 py-3 bg-gray-800 text-gray-300 rounded-xl hover:bg-gray-700 transition-all">Cancel</button>
                                <button type="submit" className="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white rounded-xl hover:from-indigo-500 hover:to-cyan-500 transition-all">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Table */}
            <div className="glass rounded-2xl overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[800px]">
                        <thead>
                            <tr className="border-b border-gray-800">
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Name</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Email</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Phone</th>
                                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Status</th>
                                <th className="text-right text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                [1, 2, 3].map(i => (
                                    <tr key={i}><td colSpan={5} className="px-6 py-4"><div className="skeleton h-6 w-full"></div></td></tr>
                                ))
                            ) : customers.length === 0 ? (
                                <tr><td colSpan={5} className="px-6 py-12 text-center text-gray-500">No customers found</td></tr>
                            ) : (
                                customers.map((c) => (
                                    <tr key={c.id} className="border-b border-gray-800/50 hover:bg-gray-800/30 transition-colors">
                                        <td className="px-6 py-4 text-white font-medium">{c.name}</td>
                                        <td className="px-6 py-4 text-gray-400">{c.email}</td>
                                        <td className="px-6 py-4 text-gray-400">{c.phone || '—'}</td>
                                        <td className="px-6 py-4"><span className={statusBadge(c.status)}>{c.status}</span></td>
                                        <td className="px-6 py-4 text-right space-x-2">
                                            <button onClick={() => handleEdit(c)} className="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition-colors">Edit</button>
                                            <button onClick={() => handleDelete(c.id)} className="text-red-400 hover:text-red-300 text-sm font-medium transition-colors">Delete</button>
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

'use client';

import { useState, useEffect } from 'react';
import { api, Invoice } from '@/lib/api';
import { formatDate } from '@/lib/format';

export default function BillingPage() {
    const [invoices, setInvoices] = useState<Invoice[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.getInvoices()
            .then((res) => setInvoices(res.data))
            .catch(console.error)
            .finally(() => setLoading(false));
    }, []);

    const statusBadge = (status: string) => {
        const colors: Record<string, string> = {
            paid: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            sent: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30',
            overdue: 'bg-red-500/15 text-red-400 border-red-500/30',
            draft: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
            void: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
        };
        return `inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium border ${colors[status] || colors.draft}`;
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold text-white">Billing</h1>
                <p className="text-gray-400 mt-1">Invoices and payment history</p>
            </div>

            <div className="flex justify-end mb-6">
                <button
                    onClick={async () => {
                        const returnUrl = window.location.origin + '/dashboard/billing';
                        try {
                            const res = await api.createSubscriptionPortal(returnUrl);
                            window.location.href = res.data.url;
                        } catch (e) { alert('Unable to launch billing portal'); }
                    }}
                    className="glass px-4 py-2 rounded-lg text-sm font-medium text-white hover:bg-white/10 transition-colors border border-white/10"
                >
                    Manage Billing in Stripe
                </button>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="glass rounded-2xl p-6 card-hover">
                    <p className="text-sm text-gray-400">Total Invoices</p>
                    <p className="text-3xl font-bold text-white mt-1">{invoices.length}</p>
                </div>
                <div className="glass rounded-2xl p-6 card-hover">
                    <p className="text-sm text-gray-400">Paid</p>
                    <p className="text-3xl font-bold text-emerald-400 mt-1">{invoices.filter(i => i.status === 'paid').length}</p>
                </div>
                <div className="glass rounded-2xl p-6 card-hover">
                    <p className="text-sm text-gray-400">Total Revenue</p>
                    <p className="text-3xl font-bold text-indigo-400 mt-1">
                        ${invoices.filter(i => i.status === 'paid').reduce((sum, i) => sum + parseFloat(i.amount), 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>

            {/* Invoices Table */}
            <div className="glass rounded-2xl overflow-hidden">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-gray-800">
                            <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Invoice #</th>
                            <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Amount</th>
                            <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Status</th>
                            <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Due Date</th>
                            <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Paid At</th>
                            <th className="text-right text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            [1, 2, 3].map(i => <tr key={i}><td colSpan={6} className="px-6 py-4"><div className="skeleton h-6 w-full"></div></td></tr>)
                        ) : invoices.length === 0 ? (
                            <tr><td colSpan={6} className="px-6 py-12 text-center text-gray-500">No invoices yet</td></tr>
                        ) : (
                            invoices.map((inv) => (
                                <tr key={inv.id} className="border-b border-gray-800/50 hover:bg-gray-800/30 transition-colors">
                                    <td className="px-6 py-4 text-white font-mono text-sm">{inv.number}</td>
                                    <td className="px-6 py-4 text-white font-medium">{inv.currency} {parseFloat(inv.amount).toFixed(2)}</td>
                                    <td className="px-6 py-4"><span className={statusBadge(inv.status)}>{inv.status}</span></td>
                                    <td className="px-6 py-4 text-gray-400">{formatDate(inv.due_date)}</td>
                                    <td className="px-6 py-4 text-gray-400">{formatDate(inv.paid_at)}</td>
                                    <td className="px-6 py-4 text-right">
                                        <a href={`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'}/invoices/${inv.id}/download`}
                                            target="_blank" rel="noopener noreferrer"
                                            className="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition-colors">
                                            Download PDF
                                        </a>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

'use client';

import { useState, useEffect } from 'react';
import { api, AdminWebhookEvent } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { 
    LuSearch, LuRefreshCw, LuEye, LuCircleAlert, 
    LuCopy, LuCheck, LuActivity, LuTerminal, LuCircleCheck, LuClock
} from 'react-icons/lu';

export default function WebhookMonitoring() {
    const [events, setEvents] = useState<AdminWebhookEvent[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalEvents, setTotalEvents] = useState(0);

    // Selected event for payload inspector modal
    const [selectedEvent, setSelectedEvent] = useState<AdminWebhookEvent | null>(null);
    const [isInspectOpen, setIsInspectOpen] = useState(false);
    
    // Copy state
    const [copied, setCopied] = useState(false);

    // Actions state
    const [reprocessLoading, setReprocessLoading] = useState(false);
    const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const fetchEvents = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {
                page: page.toString(),
                per_page: '15'
            };
            if (statusFilter !== 'all') {
                params.status = statusFilter;
            }
            if (search.trim()) {
                params.search = search;
            }
            const res = await api.getAdminWebhooks(params);
            setEvents(res.data);
            setTotalPages(res.last_page || 1);
            setTotalEvents(res.total || res.data.length);
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to fetch webhook events.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchEvents();
    }, [page, statusFilter]);

    // Handle search submission
    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setPage(1);
        fetchEvents();
    };

    // Copy JSON to clipboard
    const handleCopyPayload = () => {
        if (!selectedEvent) return;
        navigator.clipboard.writeText(JSON.stringify(selectedEvent.payload, null, 2));
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    // Reprocess webhook event
    const handleReprocess = async (event: AdminWebhookEvent) => {
        setReprocessLoading(true);
        setToast(null);
        try {
            const res = await api.reprocessAdminWebhook(event.id);
            setToast({
                type: 'success',
                message: res.message || 'Webhook event was re-processed successfully.'
            });
            // Update the selected event and the list of events locally
            if (res.data) {
                setSelectedEvent(res.data);
                setEvents(events.map(e => e.id === event.id ? res.data : e));
            } else {
                // Fallback: refresh listing
                fetchEvents();
            }
        } catch (err: any) {
            setToast({ type: 'error', message: err.message || 'Failed to reprocess webhook event.' });
        } finally {
            setReprocessLoading(false);
        }
    };

    // Status badges styling mapping
    const statusBadge = (status: string) => {
        const styles: Record<string, string> = {
            processed: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            failed: 'bg-red-500/15 text-red-400 border-red-500/30',
            processing: 'bg-amber-500/15 text-amber-400 border-amber-500/30 animate-pulse',
        };
        return `inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[status] || 'bg-gray-500/15 text-gray-400 border-gray-500/30'}`;
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-white">Webhook Monitoring</h1>
                    <p className="text-gray-400 mt-1">Audit and retry Stripe webhook delivery, inspect raw event JSON, and trace errors</p>
                </div>
                <div className="text-xs px-3 py-1 bg-gray-900/60 border border-gray-800 rounded-lg text-gray-400">
                    Total: <span className="font-semibold text-white">{totalEvents}</span> events logged
                </div>
            </div>

            {/* Toast alerts */}
            {toast && (
                <div className={`p-4 rounded-xl border flex items-center gap-3 animate-fade-in ${
                    toast.type === 'success' 
                        ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' 
                        : 'bg-red-500/15 text-red-400 border-red-500/30'
                }`}>
                    {toast.type === 'success' ? <LuCircleCheck className="text-lg flex-shrink-0" /> : <LuCircleAlert className="text-lg flex-shrink-0" />}
                    <div className="text-sm flex-1">{toast.message}</div>
                    <button onClick={() => setToast(null)} className="text-xs text-gray-400 hover:text-white uppercase font-bold tracking-wider px-2 py-1">Dismiss</button>
                </div>
            )}

            {/* Filter Panel */}
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-center bg-gray-900/20 border border-gray-800 p-4 rounded-2xl">
                <form onSubmit={handleSearchSubmit} className="relative w-full sm:max-w-md">
                    <LuSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 text-lg" />
                    <input
                        type="text"
                        placeholder="Search by event ID, type, or error..."
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

                <div className="flex items-center gap-2 w-full sm:w-auto">
                    <span className="text-xs text-gray-400 font-medium whitespace-nowrap">Filter Status:</span>
                    <select
                        value={statusFilter}
                        onChange={(e) => {
                            setPage(1);
                            setStatusFilter(e.target.value);
                        }}
                        className="w-full sm:w-40 bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-red-500/40 text-gray-300 font-medium"
                    >
                        <option value="all">All Statuses</option>
                        <option value="processed">Processed</option>
                        <option value="failed">Failed</option>
                        <option value="processing">Processing</option>
                    </select>
                </div>
            </div>

            {/* Logs Table */}
            <div className="glass rounded-2xl overflow-hidden border border-gray-800">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-800 bg-gray-900/10 text-left">
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Stripe Event ID</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Event Type</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Status</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4 text-center">Attempts</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Timestamp</th>
                                <th className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-4">Error details</th>
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
                            ) : events.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-12 text-center text-gray-500">
                                        <div className="flex flex-col items-center justify-center p-6 text-gray-400">
                                            <LuActivity className="text-3xl text-gray-600 mb-2" />
                                            <p className="font-semibold text-white">No webhooks found</p>
                                            <p className="text-xs text-gray-500 mt-1">Webhook payloads show up here once Stripe registers events.</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                events.map((event) => (
                                    <tr key={event.id} className="border-b border-gray-800/40 hover:bg-gray-800/20 transition-colors">
                                        <td className="px-6 py-4 font-mono text-xs text-white">
                                            {event.provider_event_id}
                                        </td>
                                        <td className="px-6 py-4 font-mono text-xs font-semibold text-red-400">
                                            {event.event_type}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={statusBadge(event.status)}>{event.status}</span>
                                        </td>
                                        <td className="px-6 py-4 text-center font-bold text-gray-300 text-sm">
                                            {event.attempts}
                                        </td>
                                        <td className="px-6 py-4 text-xs text-gray-400 font-medium">
                                            {formatDate(event.created_at)}
                                        </td>
                                        <td className="px-6 py-4 max-w-[200px] text-xs text-red-400/80 font-mono truncate" title={event.error || ''}>
                                            {event.error || <span className="text-gray-600 italic">None</span>}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => {
                                                        setSelectedEvent(event);
                                                        setIsInspectOpen(true);
                                                    }}
                                                    className="p-1.5 bg-gray-850 hover:bg-gray-800 text-gray-300 hover:text-white rounded-lg border border-gray-800 hover:border-gray-700 transition-all flex items-center gap-1 text-xs font-medium"
                                                    title="Inspect Event"
                                                >
                                                    <LuEye className="text-sm" /> Inspect
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
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

            {/* Modal: JSON Payload Inspector & Reprocess */}
            {isInspectOpen && selectedEvent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setIsInspectOpen(false)}></div>
                    <div className="relative w-full max-w-2xl glass border border-gray-800 rounded-2xl p-6 shadow-2xl animate-fade-in text-white space-y-6 flex flex-col max-h-[90vh]">
                        {/* Header */}
                        <div className="flex items-center justify-between border-b border-gray-800 pb-3">
                            <h3 className="text-lg font-bold flex items-center gap-2">
                                <LuTerminal className="text-red-400" />
                                Inspect Webhook Event
                            </h3>
                            <button onClick={() => setIsInspectOpen(false)} className="text-gray-400 hover:text-white uppercase font-bold text-xs">Close</button>
                        </div>

                        {/* Meta Fields Grid */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                            <div>
                                <span className="text-gray-500 font-semibold uppercase tracking-wider block">Event ID</span>
                                <span className="font-mono text-gray-300 mt-1 block">{selectedEvent.provider_event_id}</span>
                            </div>
                            <div>
                                <span className="text-gray-500 font-semibold uppercase tracking-wider block">Event Type</span>
                                <span className="font-mono text-red-400 mt-1 block font-semibold">{selectedEvent.event_type}</span>
                            </div>
                            <div>
                                <span className="text-gray-500 font-semibold uppercase tracking-wider block">Attempts</span>
                                <span className="text-gray-300 mt-1 block font-bold">{selectedEvent.attempts} times</span>
                            </div>
                            <div>
                                <span className="text-gray-500 font-semibold uppercase tracking-wider block">Status</span>
                                <span className="mt-1 block">
                                    <span className={statusBadge(selectedEvent.status)}>{selectedEvent.status}</span>
                                </span>
                            </div>
                        </div>

                        {/* Error trace box */}
                        {selectedEvent.status === 'failed' && selectedEvent.error && (
                            <div className="p-4 bg-red-950/20 border border-red-500/20 rounded-xl space-y-2">
                                <span className="text-xs font-bold text-red-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <LuCircleAlert className="text-red-400 text-sm" /> Webhook Processing Error
                                </span>
                                <pre className="text-xs text-red-300 font-mono whitespace-pre-wrap font-medium">
                                    {selectedEvent.error}
                                </pre>
                            </div>
                        )}

                        {/* Raw JSON Inspect */}
                        <div className="flex-1 flex flex-col min-h-0 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Raw JSON Payload</span>
                                <button
                                    onClick={handleCopyPayload}
                                    className="flex items-center gap-1.5 px-2.5 py-1 bg-gray-800 hover:bg-gray-700 active:scale-95 text-xs text-gray-300 rounded-lg transition-all"
                                >
                                    {copied ? (
                                        <>
                                            <LuCheck className="text-emerald-400" /> Copied!
                                        </>
                                    ) : (
                                        <>
                                            <LuCopy /> Copy Payload
                                        </>
                                    )}
                                </button>
                            </div>
                            <div className="flex-1 overflow-y-auto bg-black/40 border border-gray-800 rounded-xl p-4 min-h-[150px] font-mono text-[11px] text-indigo-300/90 leading-relaxed whitespace-pre overflow-x-auto">
                                {JSON.stringify(selectedEvent.payload, null, 2)}
                            </div>
                        </div>

                        {/* Footer actions */}
                        <div className="flex gap-3 justify-end pt-3 border-t border-gray-800">
                            <button
                                disabled={reprocessLoading}
                                onClick={() => handleReprocess(selectedEvent)}
                                className="flex items-center gap-2 px-4 py-2.5 bg-red-500 hover:bg-red-600 active:scale-95 text-xs font-bold text-white rounded-xl transition-all shadow-md shadow-red-500/20 disabled:opacity-50"
                            >
                                {reprocessLoading ? (
                                    <LuRefreshCw className="animate-spin text-sm" />
                                ) : (
                                    <LuRefreshCw className="text-sm" />
                                )}
                                Reprocess Webhook Event
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

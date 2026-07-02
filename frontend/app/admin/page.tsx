'use client';

import { useState, useEffect } from 'react';
import { api, AdminUser, SystemHealthReport, QueueStatusResponse, FailedJob } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { 
    LuRefreshCw, LuDatabase, LuServer, LuHardDrive, LuCreditCard, 
    LuTriangleAlert, LuPlay, LuCircleCheck, LuCircleX, LuLayers,
    LuTrendingUp, LuUsers, LuPercent, LuTerminal
} from 'react-icons/lu';

export default function AdminDashboard() {
    // Metrics & Users
    const [users, setUsers] = useState<AdminUser[]>([]);
    const [totalOwners, setTotalOwners] = useState(0);
    const [metricsLoading, setMetricsLoading] = useState(true);

    // Health state
    const [health, setHealth] = useState<SystemHealthReport | null>(null);
    const [healthLoading, setHealthLoading] = useState(true);
    const [healthError, setHealthError] = useState<string | null>(null);

    // Queue status state
    const [queueStatus, setQueueStatus] = useState<QueueStatusResponse | null>(null);
    const [queueLoading, setQueueLoading] = useState(true);
    const [queueError, setQueueError] = useState<string | null>(null);

    // Actions state
    const [actionRunning, setActionRunning] = useState<string | null>(null);
    const [actionStatus, setActionStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    // Expanded jobs for detail inspection
    const [expandedJobId, setExpandedJobId] = useState<number | null>(null);

    // Live refresh state
    const [isLive, setIsLive] = useState(true);

    // Fetch core dashboard metrics (from user index)
    const fetchMetrics = async () => {
        setMetricsLoading(true);
        try {
            const res = await api.getAdminUsers({ per_page: '100' });
            setUsers(res.data);
            setTotalOwners(res.total || res.data.length);
        } catch (err) {
            console.error('Failed to fetch admin users metrics', err);
        } finally {
            setMetricsLoading(false);
        }
    };

    // Fetch system health status
    const fetchHealth = async () => {
        setHealthLoading(true);
        setHealthError(null);
        try {
            const data = await api.getAdminSystemHealth();
            setHealth(data);
        } catch (err: any) {
            setHealthError(err.message || 'Failed to retrieve system health');
        } finally {
            setHealthLoading(false);
        }
    };

    // Fetch queue metrics
    const fetchQueueStatus = async () => {
        setQueueLoading(true);
        setQueueError(null);
        try {
            const data = await api.getAdminQueueStatus();
            setQueueStatus(data);
        } catch (err: any) {
            setQueueError(err.message || 'Failed to retrieve queue metrics');
        } finally {
            setQueueLoading(false);
        }
    };

    // Load initial data
    useEffect(() => {
        fetchMetrics();
        fetchHealth();
        fetchQueueStatus();
    }, []);

    // Live queue auto-refresh
    useEffect(() => {
        if (!isLive) return;
        const interval = setInterval(() => {
            fetchQueueStatus();
        }, 30000);
        return () => clearInterval(interval);
    }, [isLive]);

    // Handle job retry
    const handleRetryJob = async (jobId: string) => {
        setActionRunning(jobId);
        setActionStatus(null);
        try {
            const res = await api.retryAdminQueueJob(jobId);
            setActionStatus({ type: 'success', message: res.message || 'Job retry triggered successfully.' });
            // Refresh queue status
            await fetchQueueStatus();
        } catch (err: any) {
            setActionStatus({ type: 'error', message: err.message || 'Failed to retry job.' });
        } finally {
            setActionRunning(null);
        }
    };

    // Calculate aggregated stats
    const totalActiveSubs = users.reduce((sum, u) => sum + (u.active_subscriptions_count || 0), 0);
    // Commission: 1% of estimated total revenue (active subscriptions * estimated average price $29)
    const estimatedMonthlyCommission = users.reduce((sum, u) => sum + (u.active_subscriptions_count || 0) * 29 * 0.01, 0);

    return (
        <div className="space-y-8">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-white">Admin Dashboard</h1>
                    <p className="text-gray-400 mt-1">Platform management, queue status, and system health monitors</p>
                </div>
                <button
                    onClick={() => {
                        fetchMetrics();
                        fetchHealth();
                        fetchQueueStatus();
                    }}
                    className="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 active:scale-95 text-white text-sm font-semibold rounded-xl transition-all shadow-lg shadow-red-500/20"
                >
                    <LuRefreshCw className={`text-sm ${healthLoading || queueLoading ? 'animate-spin' : ''}`} />
                    Refresh Panel
                </button>
            </div>

            {/* Notification Bar */}
            {actionStatus && (
                <div className={`p-4 rounded-xl border flex items-center gap-3 animate-fade-in ${
                    actionStatus.type === 'success' 
                        ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' 
                        : 'bg-red-500/15 text-red-400 border-red-500/30'
                }`}>
                    {actionStatus.type === 'success' ? <LuCircleCheck className="text-lg flex-shrink-0" /> : <LuCircleX className="text-lg flex-shrink-0" />}
                    <div className="text-sm flex-1">{actionStatus.message}</div>
                    <button onClick={() => setActionStatus(null)} className="text-xs text-gray-400 hover:text-white uppercase font-bold tracking-wider px-2 py-1">Dismiss</button>
                </div>
            )}

            {/* Platform Metrics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="glass rounded-2xl p-6 card-hover relative overflow-hidden border border-gray-800">
                    <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-full blur-3xl -mr-8 -mt-8"></div>
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-400 font-medium">Platform Tenants</p>
                        <span className="p-2 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"><LuUsers /></span>
                    </div>
                    {metricsLoading ? (
                        <div className="skeleton h-10 w-24 mt-4"></div>
                    ) : (
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-4xl font-bold text-white tracking-tight">{totalOwners}</span>
                            <span className="text-xs text-gray-500">Business Owners</span>
                        </div>
                    )}
                    <p className="text-xs text-gray-500 mt-2">Active business accounts registered</p>
                </div>

                <div className="glass rounded-2xl p-6 card-hover relative overflow-hidden border border-gray-800">
                    <div className="absolute top-0 right-0 w-32 h-32 bg-emerald-500/5 rounded-full blur-3xl -mr-8 -mt-8"></div>
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-400 font-medium">Active Subscriptions</p>
                        <span className="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><LuTrendingUp /></span>
                    </div>
                    {metricsLoading ? (
                        <div className="skeleton h-10 w-24 mt-4"></div>
                    ) : (
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-4xl font-bold text-white tracking-tight">{totalActiveSubs}</span>
                            <span className="text-xs text-gray-500">End Users</span>
                        </div>
                    )}
                    <p className="text-xs text-gray-500 mt-2">Active paid & trialing clients</p>
                </div>

                <div className="glass rounded-2xl p-6 card-hover relative overflow-hidden border border-gray-800">
                    <div className="absolute top-0 right-0 w-32 h-32 bg-red-500/5 rounded-full blur-3xl -mr-8 -mt-8"></div>
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-400 font-medium">Monthly Commission Est.</p>
                        <span className="p-2 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20"><LuPercent /></span>
                    </div>
                    {metricsLoading ? (
                        <div className="skeleton h-10 w-24 mt-4"></div>
                    ) : (
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-4xl font-bold text-red-400 tracking-tight">
                                ${estimatedMonthlyCommission.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </span>
                            <span className="text-xs text-gray-500">USD</span>
                        </div>
                    )}
                    <p className="text-xs text-gray-500 mt-2">1% cut from estimated overall MRR</p>
                </div>
            </div>

            {/* System Health Section */}
            <div className="glass rounded-2xl p-6 border border-gray-800">
                <div className="flex items-center justify-between border-b border-gray-800 pb-4 mb-6">
                    <div className="flex items-center gap-3">
                        <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                        <h2 className="text-lg font-bold text-white">System Services Health</h2>
                    </div>
                    <button
                        onClick={fetchHealth}
                        className="flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-700 active:scale-95 text-xs text-gray-300 rounded-lg transition-all"
                    >
                        <LuRefreshCw className={healthLoading ? 'animate-spin' : ''} />
                        Test Services
                    </button>
                </div>

                {healthLoading ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {[1, 2, 3, 4].map(i => <div key={i} className="skeleton h-24 w-full"></div>)}
                    </div>
                ) : healthError ? (
                    <div className="p-4 bg-red-950/20 text-red-400 border border-red-500/20 rounded-xl text-sm">
                        {healthError}
                    </div>
                ) : health ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {/* Database Check */}
                        <div className={`p-4 rounded-xl border transition-all ${
                            health.services.database.status === 'up' 
                                ? 'bg-emerald-950/10 border-emerald-500/20 text-emerald-400' 
                                : 'bg-red-950/20 border-red-500/20 text-red-400'
                        }`}>
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-lg bg-gray-900/50 text-gray-300"><LuDatabase className="text-lg" /></span>
                                <div>
                                    <p className="text-xs text-gray-400 font-semibold uppercase">PostgreSQL</p>
                                    <p className="text-lg font-bold text-white capitalize mt-0.5">{health.services.database.status}</p>
                                </div>
                            </div>
                            {health.services.database.error && (
                                <p className="text-[11px] text-red-400/80 font-mono mt-2 truncate" title={health.services.database.error}>
                                    {health.services.database.error}
                                </p>
                            )}
                        </div>

                        {/* Redis Check */}
                        <div className={`p-4 rounded-xl border transition-all ${
                            health.services.redis.status === 'up' 
                                ? 'bg-emerald-950/10 border-emerald-500/20 text-emerald-400' 
                                : 'bg-red-950/20 border-red-500/20 text-red-400'
                        }`}>
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-lg bg-gray-900/50 text-gray-300"><LuServer className="text-lg" /></span>
                                <div>
                                    <p className="text-xs text-gray-400 font-semibold uppercase">Redis Cache</p>
                                    <p className="text-lg font-bold text-white capitalize mt-0.5">{health.services.redis.status}</p>
                                </div>
                            </div>
                            {health.services.redis.error && (
                                <p className="text-[11px] text-red-400/80 font-mono mt-2 truncate" title={health.services.redis.error}>
                                    {health.services.redis.error}
                                </p>
                            )}
                        </div>

                        {/* S3 Storage Check */}
                        <div className={`p-4 rounded-xl border transition-all ${
                            health.services.s3.status === 'up' 
                                ? 'bg-emerald-950/10 border-emerald-500/20 text-emerald-400' 
                                : 'bg-red-950/20 border-red-500/20 text-red-400'
                        }`}>
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-lg bg-gray-900/50 text-gray-300"><LuHardDrive className="text-lg" /></span>
                                <div>
                                    <p className="text-xs text-gray-400 font-semibold uppercase">S3 Storage</p>
                                    <p className="text-lg font-bold text-white capitalize mt-0.5">{health.services.s3.status}</p>
                                </div>
                            </div>
                            {health.services.s3.error && (
                                <p className="text-[11px] text-red-400/80 font-mono mt-2 truncate" title={health.services.s3.error}>
                                    {health.services.s3.error}
                                </p>
                            )}
                        </div>

                        {/* Stripe Check */}
                        <div className={`p-4 rounded-xl border transition-all ${
                            health.services.stripe.status === 'up' 
                                ? 'bg-emerald-950/10 border-emerald-500/20 text-emerald-400' 
                                : 'bg-red-950/20 border-red-500/20 text-red-400'
                        }`}>
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-lg bg-gray-900/50 text-gray-300"><LuCreditCard className="text-lg" /></span>
                                <div>
                                    <p className="text-xs text-gray-400 font-semibold uppercase">Stripe API</p>
                                    <p className="text-lg font-bold text-white capitalize mt-0.5">{health.services.stripe.status}</p>
                                </div>
                            </div>
                            {health.services.stripe.error && (
                                <p className="text-[11px] text-red-400/80 font-mono mt-2 truncate" title={health.services.stripe.error}>
                                    {health.services.stripe.error}
                                </p>
                            )}
                        </div>
                    </div>
                ) : null}
            </div>

            {/* Queue Metrics & Controllers */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Side: Queue Sizes & Totals */}
                <div className="glass rounded-2xl p-6 border border-gray-800 space-y-6 lg:col-span-1">
                    <div className="flex items-center justify-between border-b border-gray-800 pb-3">
                        <div className="flex items-center gap-2">
                            <LuLayers className="text-red-400 text-lg" />
                            <h3 className="font-bold text-white">Queue Metrics</h3>
                            {isLive && (
                                <span className="flex items-center gap-1.5 px-2 py-0.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span className="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">Live</span>
                                </span>
                            )}
                        </div>
                        <button
                            onClick={fetchQueueStatus}
                            className="p-1 hover:bg-gray-800 active:scale-95 text-gray-400 hover:text-white rounded"
                        >
                            <LuRefreshCw className={`text-xs ${queueLoading ? 'animate-spin' : ''}`} />
                        </button>
                    </div>

                    {queueLoading ? (
                        <div className="space-y-3">
                            {[1, 2, 3, 4, 5].map(i => <div key={i} className="skeleton h-10 w-full"></div>)}
                        </div>
                    ) : queueStatus ? (
                        <div className="space-y-4">
                            {/* Queue List */}
                            <div className="space-y-2">
                                {Object.entries(queueStatus.metrics.queue_sizes).map(([queueName, size]) => (
                                    <div key={queueName} className="flex justify-between items-center px-4 py-2.5 bg-gray-900/40 rounded-xl border border-gray-800/50">
                                        <span className="text-sm text-gray-400 font-medium font-mono">{queueName}</span>
                                        <span className={`px-2 py-0.5 rounded-lg text-xs font-bold ${
                                            size > 50 ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 
                                            size > 10 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' :
                                            'bg-gray-800 text-gray-300'
                                        }`}>{size} jobs</span>
                                    </div>
                                ))}
                            </div>

                            {/* Failed count card */}
                            <div className="p-4 rounded-xl bg-red-950/10 border border-red-500/20 text-center">
                                <p className="text-xs text-gray-400 font-semibold uppercase tracking-wider">Total Failed Jobs</p>
                                <p className="text-4xl font-extrabold text-red-400 mt-1">{queueStatus.metrics.failed_jobs_count}</p>
                                
                                {queueStatus.metrics.failed_jobs_count > 0 && (
                                    <button
                                        disabled={actionRunning !== null}
                                        onClick={() => handleRetryJob('all')}
                                        className="mt-4 w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-500/25 hover:bg-red-500 hover:text-white border border-red-500/30 hover:border-transparent text-red-400 text-xs font-bold rounded-xl transition-all disabled:opacity-50"
                                    >
                                        {actionRunning === 'all' ? (
                                            <LuRefreshCw className="animate-spin" />
                                        ) : (
                                            <LuPlay />
                                        )}
                                        Retry All Failed Jobs
                                    </button>
                                )}
                            </div>
                        </div>
                    ) : (
                        <p className="text-gray-500 text-center text-sm">Failed to load queue details</p>
                    )}
                </div>

                {/* Right Side: Failed Jobs Listing */}
                <div className="glass rounded-2xl p-6 border border-gray-800 lg:col-span-2 space-y-4 flex flex-col">
                    <div className="flex items-center justify-between border-b border-gray-800 pb-3">
                        <div className="flex items-center gap-2">
                            <LuTriangleAlert className="text-red-400 text-lg" />
                            <h3 className="font-bold text-white">Recent Failed Jobs</h3>
                        </div>
                    </div>

                    <div className="flex-1 overflow-x-auto min-h-[300px]">
                        {queueLoading ? (
                            <div className="space-y-4 py-4">
                                {[1, 2, 3].map(i => <div key={i} className="skeleton h-14 w-full"></div>)}
                            </div>
                        ) : queueStatus?.failed_jobs?.data && queueStatus.failed_jobs.data.length > 0 ? (
                            <div className="space-y-2">
                                {queueStatus.failed_jobs.data.map((job) => {
                                    const isExpanded = expandedJobId === job.id;
                                    // Parse exception to get main message
                                    const mainErrorMessage = job.exception.split('\n')[0] || 'Unknown Exception';

                                    return (
                                        <div key={job.id} className="border border-gray-800 rounded-xl bg-gray-900/20 overflow-hidden transition-all duration-300">
                                            {/* Header */}
                                            <div 
                                                onClick={() => setExpandedJobId(isExpanded ? null : job.id)}
                                                className="p-4 flex items-center justify-between gap-4 cursor-pointer hover:bg-gray-800/25 transition-colors"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2 flex-wrap">
                                                        <span className="text-xs px-2 py-0.5 bg-gray-800 text-gray-300 rounded font-mono font-medium">
                                                            {job.queue}
                                                        </span>
                                                        <span className="text-[11px] text-gray-500 font-mono">
                                                            {job.uuid.substring(0, 8)}...
                                                        </span>
                                                        <span className="text-xs text-gray-500 font-medium ml-auto">
                                                            {formatDate(job.failed_at)}
                                                        </span>
                                                    </div>
                                                    <p className="text-sm font-semibold text-red-400 mt-1 truncate max-w-full">
                                                        {mainErrorMessage}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        disabled={actionRunning !== null}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleRetryJob(job.uuid);
                                                        }}
                                                        className="p-2 bg-red-500/10 hover:bg-red-500 hover:text-white text-red-400 rounded-lg border border-red-500/20 hover:border-transparent transition-all disabled:opacity-50"
                                                        title="Retry Job"
                                                    >
                                                        {actionRunning === job.uuid ? (
                                                            <LuRefreshCw className="animate-spin text-sm" />
                                                        ) : (
                                                            <LuPlay className="text-sm" />
                                                        )}
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Details Accordion */}
                                            {isExpanded && (
                                                <div className="px-4 pb-4 border-t border-gray-800 bg-black/30 p-4 space-y-4">
                                                    <div>
                                                        <span className="text-xs font-semibold text-gray-400 flex items-center gap-1.5 mb-1"><LuTerminal /> Connection details</span>
                                                        <p className="text-xs text-gray-300 font-mono bg-gray-900/50 p-2.5 rounded-lg border border-gray-800">
                                                            {job.connection} ({job.queue})
                                                        </p>
                                                    </div>
                                                    
                                                    <div>
                                                        <span className="text-xs font-semibold text-gray-400 flex items-center gap-1.5 mb-1"><LuTerminal /> Exception Stack Trace</span>
                                                        <pre className="text-[11px] text-gray-400 font-mono bg-gray-900/50 p-3 rounded-lg border border-gray-800 overflow-x-auto max-h-48 whitespace-pre-wrap">
                                                            {job.exception}
                                                        </pre>
                                                    </div>

                                                    <div className="text-[11px] text-gray-500">
                                                        Job ID: {job.id} &bull; UUID: {job.uuid}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="h-full flex flex-col items-center justify-center text-center p-6 bg-gray-900/10 rounded-2xl border border-dashed border-gray-800 py-12">
                                <LuCircleCheck className="text-3xl text-emerald-400/80 mb-2 animate-bounce" />
                                <p className="text-sm font-semibold text-white">All queues cleared!</p>
                                <p className="text-xs text-gray-500 mt-1">There are no failed jobs recorded in the database.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

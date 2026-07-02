'use client';

import { useState, useEffect } from 'react';
import { api, DashboardData } from '@/lib/api';

function StatCard({ title, value, subtitle, color, glowClass, delayClass }: { title: string; value: string; subtitle?: string; color: string; glowClass?: string; delayClass?: string }) {
    return (
        <div className={`glass rounded-[16px] p-6 card-hover stagger-enter ${delayClass || ''} relative overflow-hidden group`}>
            {/* Subtle glow on hover */}
            <div className="absolute inset-0 bg-white opacity-0 group-hover:opacity-[0.02] transition-opacity duration-300"></div>
            
            {glowClass && (
                <div className={`absolute -right-10 -top-10 w-32 h-32 opacity-20 blur-[40px] rounded-full pointer-events-none ${glowClass}`}></div>
            )}
            
            <div className="relative z-10">
                <p className="text-[12px] font-semibold tracking-[0.05em] uppercase text-[var(--color-on-surface-variant)] mb-2">{title}</p>
                <p className={`text-[32px] font-bold tracking-[-0.03em] ${color}`}>{value}</p>
                {subtitle && <p className="text-[14px] text-[var(--color-on-surface-variant)] mt-3 pt-3 border-t border-white/5">{subtitle}</p>}
            </div>
        </div>
    );
}

export default function DashboardPage() {
    const [data, setData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.getDashboard()
            .then((res) => setData(res.data))
            .catch(console.error)
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="space-y-8 animate-pulse">
                <div className="stagger-enter">
                    <div className="h-10 w-48 bg-white/5 rounded-lg mb-2"></div>
                    <div className="h-5 w-64 bg-white/5 rounded-md"></div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {[1, 2, 3, 4].map(i => <div key={i} className="skeleton h-[160px]"></div>)}
                </div>
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="skeleton h-[360px]"></div>
                    <div className="skeleton h-[360px]"></div>
                </div>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="flex flex-col items-center justify-center py-32 text-center glass rounded-2xl border border-white/5">
                <p className="text-[var(--color-error)] text-lg mb-2 font-medium">Unable to load dashboard data</p>
                <p className="text-[var(--color-on-surface-variant)]">Please check your connection and try again.</p>
            </div>
        );
    }

    const formatCurrency = (n: number) => `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    return (
        <div className="space-y-8">
            <div className="stagger-enter relative z-10">
                <h1 className="text-[32px] md:text-[48px] font-bold text-white tracking-[-0.04em] leading-[1.1]">Dashboard</h1>
                <p className="text-[16px] text-[var(--color-on-surface-variant)] mt-2 tracking-[-0.01em]">Overview of your subscription business</p>
            </div>

            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
                <StatCard 
                    delayClass="delay-1" 
                    title="Monthly Recurring Revenue" 
                    value={formatCurrency(data.mrr)} 
                    subtitle={`ARR: ${formatCurrency(data.arr)}`} 
                    color="text-[var(--color-secondary)]" 
                    glowClass="bg-[#10b981]" 
                />
                <StatCard 
                    delayClass="delay-2" 
                    title="Active Subscribers" 
                    value={data.active_subscribers.toLocaleString()} 
                    subtitle={`+${data.new_subscribers_this_month} this month`} 
                    color="text-[var(--color-tertiary)]" 
                    glowClass="bg-[#3b82f6]"
                />
                <StatCard 
                    delayClass="delay-3" 
                    title="Churn Rate" 
                    value={`${data.churn_rate}%`} 
                    subtitle={`${data.canceled_this_month} canceled this month`} 
                    color={data.churn_rate > 5 ? 'text-[var(--color-error)]' : 'text-white'} 
                    glowClass={data.churn_rate > 5 ? 'bg-[#f43f5e]' : ''}
                />
                <StatCard 
                    delayClass="delay-4" 
                    title="Customer LTV" 
                    value={formatCurrency(data.ltv)} 
                    subtitle={`Revenue: ${formatCurrency(data.total_revenue)}`} 
                    color="text-amber-300" 
                    glowClass="bg-amber-400"
                />
                <StatCard 
                    delayClass="delay-5" 
                    title="NRR" 
                    value={`${data.nrr}%`} 
                    color="text-emerald-400" 
                    glowClass="bg-emerald-400"
                />
                <StatCard 
                    delayClass="delay-5" 
                    title="Expansion MRR" 
                    value={formatCurrency(data.expansion_mrr)} 
                    color="text-indigo-400" 
                    glowClass="bg-[#3b82f6]"
                />
            </div>

            {/* Action Row */}
            <div className="flex gap-4 relative z-10">
                <button
                    onClick={() => api.exportDashboardPdf().then(blob => { const url = URL.createObjectURL(blob); window.open(url); })}
                    className="glass px-4 py-2 rounded-lg text-sm font-medium text-white hover:bg-white/10 transition-colors border border-white/10"
                >
                    Export PDF
                </button>
                <button
                    onClick={() => api.exportDashboardCsv().then(blob => { const url = URL.createObjectURL(blob); window.open(url); })}
                    className="glass px-4 py-2 rounded-lg text-sm font-medium text-white hover:bg-white/10 transition-colors border border-white/10"
                >
                    Export CSV
                </button>
            </div>

            {/* Charts Row */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 relative z-10">
                {/* Subscriber Growth */}
                <div className="glass rounded-[16px] p-8 stagger-enter delay-3 card-hover">
                    <h3 className="text-[20px] font-semibold text-white mb-6 tracking-[-0.02em]">Subscriber Growth <span className="text-[var(--color-on-surface-variant)] font-normal text-[14px] ml-2 tracking-normal">(6 Months)</span></h3>
                    <div className="space-y-4">
                        {data.subscriber_growth.slice(-6).map((month, idx) => {
                            const maxVal = Math.max(...data.subscriber_growth.map(m => m.new + m.canceled), 1);
                            return (
                                <div key={month.month} className="flex items-center gap-4 group">
                                    <span className="text-[14px] font-medium text-[var(--color-on-surface-variant)] w-12 group-hover:text-white transition-colors">{month.label.split(' ')[0]}</span>
                                    <div className="flex-1 flex gap-[2px]">
                                        <div className="h-8 bg-[var(--color-secondary-container)]/80 rounded-l-[4px] rounded-r-[2px] flex items-center px-3 transition-all duration-1000 ease-out group-hover:brightness-110"
                                            style={{ width: `${Math.max((month.new / maxVal) * 100, 5)}%`, transitionDelay: `${idx * 100}ms` }}>
                                            <span className="text-[12px] text-[var(--color-surface)] font-bold">{month.new > 0 ? `+${month.new}` : ''}</span>
                                        </div>
                                        {month.canceled > 0 && (
                                            <div className="h-8 bg-[var(--color-error-container)]/80 rounded-l-[2px] rounded-r-[4px] flex items-center px-3 transition-all duration-1000 ease-out group-hover:brightness-110"
                                                style={{ width: `${Math.max((month.canceled / maxVal) * 100, 5)}%`, transitionDelay: `${idx * 100 + 200}ms` }}>
                                                <span className="text-[12px] text-[var(--color-error)] font-bold">-{month.canceled}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Revenue by Plan & Conversions Stack */}
                <div className="space-y-6">
                    {/* Revenue by Plan */}
                    <div className="glass rounded-[16px] p-8 stagger-enter delay-4 card-hover">
                        <h3 className="text-[20px] font-semibold text-white mb-6 tracking-[-0.02em]">Revenue by Plan</h3>
                        {data.revenue_by_plan.length === 0 ? (
                            <p className="text-[var(--color-on-surface-variant)] text-center py-8">No revenue data yet</p>
                        ) : (
                            <div className="space-y-5">
                                {data.revenue_by_plan.map((plan, i) => {
                                    const maxRev = Math.max(...data.revenue_by_plan.map(p => p.total_revenue), 1);
                                    const colors = ['bg-[#3b82f6]', 'bg-[#0ea5e9]', 'bg-[#10b981]', 'bg-[#f59e0b]'];
                                    return (
                                        <div key={i} className="group">
                                            <div className="flex justify-between text-[14px] mb-2">
                                                <span className="text-[var(--color-primary)] font-medium group-hover:text-white transition-colors">{plan.name}</span>
                                                <div className="text-right">
                                                    <span className="text-white font-medium block">{formatCurrency(plan.total_revenue)}</span>
                                                    <span className="text-[12px] text-[var(--color-on-surface-variant)]">{plan.subscriber_count} subs</span>
                                                </div>
                                            </div>
                                            <div className="h-2 bg-white/5 rounded-full overflow-hidden">
                                                <div className={`h-full ${colors[i % colors.length]} rounded-full transition-all duration-1000 ease-out`}
                                                    style={{ width: `${(plan.total_revenue / maxRev) * 100}%` }}></div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Trial Conversions */}
                    <div className="glass rounded-[16px] p-8 stagger-enter delay-4 card-hover flex flex-col sm:flex-row items-center gap-8 justify-between relative overflow-hidden">
                        <div className="absolute right-0 bottom-0 w-48 h-48 bg-[#3b82f6] opacity-[0.05] blur-[50px] rounded-full"></div>
                        
                        <div>
                            <h3 className="text-[20px] font-semibold text-white mb-2 tracking-[-0.02em]">Trial Conversions</h3>
                            <p className="text-[14px] text-[var(--color-on-surface-variant)] mb-4">Last 3 months performance</p>
                            <div className="space-y-1">
                                <p className="text-[14px] text-[var(--color-on-surface-variant)]">Total Trials: <span className="text-white font-medium ml-1">{data.trial_conversions.total_trials}</span></p>
                                <p className="text-[14px] text-[var(--color-on-surface-variant)]">Converted: <span className="text-[var(--color-secondary)] font-medium ml-1">{data.trial_conversions.converted}</span></p>
                            </div>
                        </div>

                        <div className="relative w-28 h-28 shrink-0">
                            <svg className="w-28 h-28 -rotate-90 transform origin-center drop-shadow-lg" viewBox="0 0 36 36">
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none" stroke="rgba(255,255,255,0.05)" strokeWidth="3" />
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none" stroke="#3b82f6" strokeWidth="3"
                                    strokeDasharray={`${data.trial_conversions.rate}, 100`}
                                    strokeLinecap="round" className="transition-all duration-1000 ease-out" />
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center">
                                <span className="text-[24px] font-bold text-white tracking-[-0.02em]">{data.trial_conversions.rate}%</span>
                                <span className="text-[10px] uppercase tracking-[0.05em] text-[var(--color-on-surface-variant)]">Rate</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

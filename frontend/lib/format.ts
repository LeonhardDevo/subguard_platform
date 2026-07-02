export function formatDate(dateString: string | null | undefined): string {
    if (!dateString) return '—';
    try {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            timeZone: 'UTC' // Since it's a Z string
        }).format(date);
    } catch (e) {
        return dateString;
    }
}

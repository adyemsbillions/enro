export const fmt = (n: number) => Math.round(n).toLocaleString('en-NG');
export const naira = (n: number) => '₦' + fmt(n);

export function ago(dt: string): string {
  const t = new Date(dt.replace(' ', 'T') + '+01:00').getTime(); // server time is Africa/Lagos
  const d = Math.max(0, (Date.now() - t) / 1000);
  if (d < 60) return 'just now';
  if (d < 3600) return Math.floor(d / 60) + 'm';
  if (d < 86400) return Math.floor(d / 3600) + 'h';
  if (d < 86400 * 7) return Math.floor(d / 86400) + 'd';
  return new Date(t).toLocaleDateString('en-NG', { month: 'short', day: 'numeric' });
}

export function dateLabel(dt: string): string {
  const t = new Date(dt.replace(' ', 'T') + '+01:00');
  return t.toLocaleString('en-NG', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export function greeting(): string {
  const h = new Date().getHours();
  return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

/**
 * Attendance: clock in / out
 */
import { api } from './core.js';

export function handleAttendanceClick(t, e, ctx) {
    const { openConfirm, openNotice } = ctx;

    if (t.closest('[data-clock-in]')) {
        e.preventDefault();
        openConfirm(
            'Clock in?',
            'Start your shift now? This will be recorded for today.',
            () => {
                api('/api/staff/attendance/clock-in', { method: 'POST', body: '{}' })
                    .then((r) => {
                        openNotice('Clock in', r.message || 'Clocked in');
                        ctx.loadStaffBootstrap?.();
                    })
                    .catch((err) => openNotice('Clock in', err.message));
            }
        );
        return true;
    }
    if (t.closest('[data-clock-out]')) {
        e.preventDefault();
        openConfirm(
            'Clock out?',
            'End your shift now? Make sure you are done for the day.',
            () => {
                api('/api/staff/attendance/clock-out', { method: 'POST', body: '{}' })
                    .then((r) => {
                        openNotice('Clock out', r.message || 'Clocked out');
                        ctx.loadStaffBootstrap?.();
                    })
                    .catch((err) => openNotice('Clock out', err.message));
            }
        );
        return true;
    }
    return false;
}

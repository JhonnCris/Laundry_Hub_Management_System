/**
 * Attendance: clock in / out
 */
import { api } from './core.js';

export function handleAttendanceClick(t, e, ctx) {
    const { openConfirm, openNotice, openError } = ctx;

    if (t.closest('[data-clock-in]')) {
        e.preventDefault();
        openConfirm(
            'Clock in?',
            'Start your shift now? This will be recorded for today.',
            () => {
                api('/ajax/staff/attendance/clock-in', { method: 'POST', body: '{}' })
                    .then((r) => {
                        openNotice('Clock in', r.message || 'Clocked in', 'success');
                        ctx.loadStaffBootstrap?.();
                    })
                    .catch((err) => openError('Clock in', err));
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
                api('/ajax/staff/attendance/clock-out', { method: 'POST', body: '{}' })
                    .then((r) => {
                        openNotice('Clock out', r.message || 'Clocked out', 'success');
                        ctx.loadStaffBootstrap?.();
                    })
                    .catch((err) => openError('Clock out', err));
            }
        );
        return true;
    }
    return false;
}

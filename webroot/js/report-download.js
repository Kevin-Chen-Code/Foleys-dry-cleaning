/* A download is a state-changing POST. Refresh only after the Excel response is received. */
(() => {
    'use strict';
    const form = document.querySelector('[data-report-download]');
    if (!form) return;
    const status = document.querySelector('[data-report-download-status]');
    const button = form.querySelector('button');
    let downloading = false;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (downloading) return;
        downloading = true;
        button.disabled = true;
        status.hidden = false;
        status.style.color = '';
        status.textContent = 'Preparing Excel download…';
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
            });
            if (!response.ok || !response.headers.get('content-type')?.includes('spreadsheetml.sheet')) {
                throw new Error('Download failed');
            }
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'foleys-weekly-report.xlsx';
            document.body.appendChild(link);
            link.click();
            link.remove();
            // Allow the browser to start saving the blob before refreshing the report.
            setTimeout(() => { URL.revokeObjectURL(url); window.location.reload(); }, 1000);
        } catch {
            status.style.color = '#b42318';
            status.textContent = 'The download could not be completed. Refresh this page to check the report before trying again. Requests remain saved in Incoming requests.';
            button.disabled = false;
            downloading = false;
        }
    });
})();

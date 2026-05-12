/**
 * Documents Card — client-side interactions.
 *
 * Handles:
 *   - "Generate Step N" button → AJAX → refresh file list
 *   - "Generate Full Packet" button → AJAX → refresh file list
 *   - Status / error message display
 *   - Dynamic file list re-render after generation
 *
 * Depends on: azDocs.ajax_url, azDocs.nonce (localized from PHP)
 */
(function ($) {
    'use strict';

    var card     = null;
    var statusEl = null;
    var filesEl  = null;

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    $(function () {
        card     = $('#az-docs-card');
        if (!card.length) return;

        statusEl = card.find('#az-docs-status');
        filesEl  = card.find('#az-docs-files');

        // Step generate buttons
        card.on('click', '.az-docs__step-btn[data-action="generate_step"]', function () {
            var btn    = $(this);
            var step   = parseInt(btn.data('step'), 10);
            var caseId = parseInt(btn.data('case-id'), 10);
            if (!step || !caseId) return;
            runGeneration(btn, 'az_generate_step', { step: step, case_id: caseId });
        });

        // Full packet button
        card.on('click', '.az-docs__full-btn[data-action="generate_full"]', function () {
            var btn    = $(this);
            var caseId = parseInt(btn.data('case-id'), 10);
            if (!caseId) return;
            runGeneration(btn, 'az_generate_full', { case_id: caseId });
        });
    });

    // -------------------------------------------------------------------------
    // AJAX generation
    // -------------------------------------------------------------------------

    function runGeneration(triggerBtn, action, postData) {
        // Lock all buttons
        setLoading(true, triggerBtn);
        clearStatus();

        $.ajax({
            url:    azDocs.ajax_url,
            method: 'POST',
            data:   $.extend({ action: action, _wpnonce: azDocs.nonce }, postData),
            timeout: 120000 // 2 min; generation can take a while
        })
        .done(function (res) {
            if (res.success) {
                showStatus('success', res.data.message || 'Documents generated.');
                // Refresh file list
                if (res.data.files && res.data.files.length) {
                    renderFileList(res.data.files, res.data.zip_url);
                }
                // If errors also present (partial success)
                if (res.data.errors && res.data.errors.length) {
                    showStatus('warning',
                        (res.data.message || '') + '<br>' +
                        '<small>Issues: ' + escHtml(res.data.errors.join('; ')) + '</small>'
                    );
                }
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'An error occurred.';
                showStatus('error', msg);
            }
        })
        .fail(function (xhr) {
            var msg = 'Request failed (HTTP ' + xhr.status + ').';
            if (xhr.status === 0) {
                msg = 'Connection error — please check your internet connection.';
            } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            } else if (xhr.responseText) {
                // Show raw response snippet to help debug PHP errors
                var snippet = xhr.responseText.substring(0, 300);
                msg = 'Server error: <code style="font-size:0.8em;word-break:break-all;">' + escHtml(snippet) + '</code>';
            }
            showStatus('error', msg);
        })
        .always(function () {
            setLoading(false, triggerBtn);
        });
    }

    // -------------------------------------------------------------------------
    // UI helpers
    // -------------------------------------------------------------------------

    function setLoading(loading, activeBtn) {
        var allBtns = card.find('.az-docs__step-btn, .az-docs__full-btn');
        if (loading) {
            card.addClass('az-docs--loading');
            // Disable all buttons to prevent double-submission
            allBtns.prop('disabled', true);
            // Show spinner only on the button that was clicked
            activeBtn.find('.az-docs__step-btn-spinner, .az-docs__full-btn-spinner').removeAttr('hidden');
        } else {
            card.removeClass('az-docs--loading');
            // Hide spinner on the active button first
            activeBtn.find('.az-docs__step-btn-spinner, .az-docs__full-btn-spinner').attr('hidden', 'hidden');
            // Re-enable all buttons unless the card was originally locked (no questionnaire)
            if (!card.find('.az-docs__actions').hasClass('az-docs__actions--disabled')) {
                allBtns.prop('disabled', false);
            }
        }
    }

    function clearStatus() {
        statusEl.hide().html('').removeAttr('hidden');
    }

    function showStatus(type, message) {
        var icons = {
            success: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            error:   '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            warning: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
        };
        var icon = icons[type] || '';
        statusEl
            .attr('class', 'az-docs__notice az-docs__notice--' + type)
            .removeAttr('hidden')
            .html(icon + '<span>' + message + '</span>')
            .show();
    }

    // -------------------------------------------------------------------------
    // File list renderer
    // -------------------------------------------------------------------------

    function renderFileList(files, zipUrl) {
        if (!files || !files.length) return;

        // Group by step
        var byStep = {};
        files.forEach(function (f) {
            var s = f.step || 0;
            if (!byStep[s]) byStep[s] = [];
            byStep[s].push(f);
        });

        var stepLabels = {
            1: 'Step 1 — Initial Filing',
            2: 'Step 2 — Serving the Spouse',
            3: 'Step 3 — Default Application',
            4: 'Step 4 — Default Hearing / Decree'
        };

        var html = '';

        // Header with ZIP link
        html += '<div class="az-docs__files-header">';
        html += '<h3 class="az-docs__files-title">Generated Documents</h3>';
        if (zipUrl) {
            html += '<a href="' + escHtml(zipUrl) + '" class="az-docs__zip-btn" download>';
            html += '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
            html += 'Download ZIP</a>';
        }
        html += '</div>';

        html += '<div class="az-docs__file-list">';

        var sortedSteps = Object.keys(byStep).map(Number).sort(function(a,b){return a-b;});
        sortedSteps.forEach(function (step) {
            var label = stepLabels[step] || 'Other';
            html += '<div class="az-docs__step-group">';
            html += '<div class="az-docs__step-group-label">' + escHtml(label) + '</div>';

            byStep[step].forEach(function (file) {
                var name = (file.name || '').replace(/^step\d+_/, '').replace(/_/g, ' ').replace(/\.pdf$/i, '');
                var kb   = file.size ? (file.size / 1024).toFixed(1) + ' KB' : '';

                html += '<div class="az-docs__file-row">';
                html += '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="az-docs__file-icon" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>';
                html += '<span class="az-docs__file-name">' + escHtml(name) + '</span>';
                html += '<span class="az-docs__file-size">' + escHtml(kb) + '</span>';
                html += '<a href="' + escHtml(file.download_url) + '" class="az-docs__file-dl" download aria-label="Download ' + escHtml(name) + '">';
                html += '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
                html += '</a>';
                html += '</div>';
            });

            html += '</div>';
        });

        html += '</div>';

        filesEl.html(html);
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

}(jQuery));

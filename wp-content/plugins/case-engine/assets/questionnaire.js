/**
 * AZ Divorce — Questionnaire Wizard JS
 *
 * Handles:
 *  - Step navigation (stepper + prev/next buttons)
 *  - Per-step AJAX save with debounced autosave
 *  - Repeatable property / debt rows
 *  - Conditional field visibility (former name)
 *  - Client-side validation
 *  - Progress bar + stepper updates
 *  - Tooltip accessibility
 *
 * Depends on:  jQuery, azQuestionnaire (wp_localize_script)
 */
/* global azQuestionnaire, jQuery */
(function ($) {
	'use strict';

	/* ── Config ─────────────────────────────────────── */
	var cfg = azQuestionnaire;
	var TOTAL_STEPS = cfg.totalSteps || 7;
	var autosaveDelay = 2000;   // ms debounce
	var autosaveTimer = null;

	/* ── State ───────────────────────────────────────── */
	var $wrapper        = $('#az-questionnaire');
	var currentStep     = parseInt($wrapper.data('current-step'), 10) || 1;
	var completedSteps  = parseCompletedSteps($wrapper.data('completed-steps'));

	/* ── Init ────────────────────────────────────────── */
	function init() {
		if (!$wrapper.length) { return; }

		loadPrefillData();
		showStep(currentStep, false);
		updateStepper();
		updateProgressBar();

		// Step buttons in stepper nav
		$wrapper.on('click', '.az-q-step[data-step]', function () {
			var step = parseInt($(this).data('step'), 10);
			if (completedSteps.indexOf(step) !== -1 || step === currentStep) {
				showStep(step);
			}
		});

		// Save & Continue buttons
		$wrapper.on('click', '.az-q-btn-save', function () {
			var step = parseInt($(this).data('step'), 10);
			saveStep(step, true);
		});

		// Previous buttons
		$wrapper.on('click', '.az-q-btn-prev', function () {
			var prev = parseInt($(this).data('prev'), 10);
			showStep(prev, false);
		});

		// Repeater add/remove
		$wrapper.on('click', '#add-property-row',  addPropertyRow);
		$wrapper.on('click', '#add-debt-row',       addDebtRow);
		$wrapper.on('click', '.az-q-repeater__remove', removeRepeaterRow);

		// Conditional: restore former name
		$wrapper.on('change', 'input[name="restore_former_name"]', toggleFormerName);
		// Run once on init
		toggleFormerName();

		// Autosave on blur of any input/select/textarea inside wizard
		$wrapper.on('blur', 'input, select, textarea', function () {
			clearTimeout(autosaveTimer);
			autosaveTimer = setTimeout(function () {
				var $panel = $wrapper.find('.az-q-panel:not([hidden])');
				if ($panel.length) {
					var step = parseInt($panel.data('step'), 10);
					if (step >= 1 && step <= TOTAL_STEPS) {
						saveStep(step, false);
					}
				}
			}, autosaveDelay);
		});
	}

	/* ── Step navigation ─────────────────────────────── */
	function showStep(step, animate) {
		animate = (animate === undefined) ? true : animate;
		$wrapper.find('.az-q-panel').each(function () {
			var s = parseInt($(this).data('step'), 10);
			if (s === step || $(this).is('#az-q-panel-complete')) {
				$(this).removeAttr('hidden');
			} else {
				$(this).attr('hidden', '');
			}
		});

		if (step === 'complete') {
			$wrapper.find('#az-q-panel-complete').removeAttr('hidden');
			$wrapper.find('.az-q-panel[data-step]').attr('hidden', '');
			return;
		}

		$wrapper.find('#az-q-panel-complete').attr('hidden', '');
		currentStep = step;
		updateStepper();
		updateProgressBar();

		if (animate) {
			$('html, body').animate({ scrollTop: $wrapper.offset().top - 20 }, 250);
		}
	}

	/* ── Stepper update ──────────────────────────────── */
	function updateStepper() {
		$wrapper.find('.az-q-step[data-step]').each(function () {
			var step = parseInt($(this).data('step'), 10);
			var done = completedSteps.indexOf(step) !== -1;
			var active = step === currentStep;
			$(this)
				.toggleClass('az-q-step--done',   done)
				.toggleClass('az-q-step--active', active)
				.prop('disabled', !done && !active);

			var $num = $(this).find('.az-q-step__num');
			if (done) {
				$num.html('<span class="az-q-step__check" aria-hidden="true">&#10003;</span>');
			} else {
				$num.text(step);
			}
		});
		// Connectors
		$wrapper.find('.az-q-step__connector').each(function (i) {
			var stepNum = i + 1;
			$(this).toggleClass('az-q-step__connector--done', completedSteps.indexOf(stepNum) !== -1);
		});
	}

	/* ── Progress bar ────────────────────────────────── */
	function updateProgressBar() {
		var pct = Math.min(100, Math.round((completedSteps.length / TOTAL_STEPS) * 100));
		$('#az-q-progress-bar').css('width', pct + '%');
	}

	/* ── AJAX save ───────────────────────────────────── */
	function saveStep(step, navigateNext) {
		if (!validateStep(step)) { return; }

		var caseId = parseInt($wrapper.data('case-id'), 10);
		var data   = collectStepData(step, caseId);

		setSaveStatus('saving');

		$.ajax({
			url:    cfg.ajaxUrl,
			type:   'POST',
			data:   data,
			success: function (resp) {
				if (resp.success) {
					setSaveStatus('saved');
					completedSteps = resp.data.completed_steps || completedSteps;
					if (completedSteps.indexOf(step) === -1) {
						completedSteps.push(step);
					}
					completedSteps.sort(function (a, b) { return a - b; });
					updateStepper();
					updateProgressBar();

					if (resp.data.is_complete) {
						showStep('complete');
						return;
					}
					if (navigateNext) {
						var next = Math.min(step + 1, TOTAL_STEPS);
						showStep(next);
					}
				} else {
					setSaveStatus('error', resp.data && resp.data.message ? resp.data.message : cfg.i18n.saveError);
				}
			},
			error: function () {
				setSaveStatus('error', cfg.i18n.saveError);
			}
		});
	}

	/* ── Collect step form data ──────────────────────── */
	function collectStepData(step, caseId) {
		var data = {
			action:  'az_questionnaire_save',
			nonce:   cfg.nonce,
			case_id: caseId,
			step:    step,
		};

		var $panel = $('#az-q-panel-' + step);

		if (step === 5) {
			// Repeater: property and debt
			$panel.find('#property-rows .az-q-repeater__row').each(function (i) {
				data['property_items[' + i + '][description]'] = $(this).find('input[name*="[description]"]').val() || '';
				data['property_items[' + i + '][value]']       = $(this).find('input[name*="[value]"]').val() || '';
				data['property_items[' + i + '][awarded_to]']  = $(this).find('select[name*="[awarded_to]"]').val() || 'petitioner';
			});
			$panel.find('#debt-rows .az-q-repeater__row').each(function (i) {
				data['debt_items[' + i + '][creditor]']          = $(this).find('input[name*="[creditor]"]').val() || '';
				data['debt_items[' + i + '][balance]']           = $(this).find('input[name*="[balance]"]').val() || '';
				data['debt_items[' + i + '][responsible_party]'] = $(this).find('select[name*="[responsible_party]"]').val() || 'petitioner';
			});
		} else {
			// Regular fields
			$panel.find('input:not([type="radio"]), select').each(function () {
				var name = $(this).attr('name');
				if (name) { data[name] = $(this).val() || ''; }
			});
			// Radio buttons: take checked value
			var radioNames = {};
			$panel.find('input[type="radio"]').each(function () {
				radioNames[$(this).attr('name')] = true;
			});
			$.each(radioNames, function (name) {
				var val = $panel.find('input[name="' + name + '"]:checked').val();
				data[name] = val || 'no';
			});
		}

		return data;
	}

	/* ── Client-side validation ──────────────────────── */
	function validateStep(step) {
		var $panel  = $('#az-q-panel-' + step);
		var valid   = true;

		// Clear previous errors
		$panel.find('.az-q-invalid').removeClass('az-q-invalid');
		$panel.find('.az-q-field__error').remove();

		// Required text / select / date fields
		$panel.find('[required]').each(function () {
			var $f = $(this);
			if (!$.trim($f.val())) {
				markInvalid($f, cfg.i18n.required);
				valid = false;
			}
		});

		// Email validation
		$panel.find('input[type="email"]').each(function () {
			var $f = $(this);
			var v  = $.trim($f.val());
			if (v && !isValidEmail(v)) {
				markInvalid($f, cfg.i18n.invalidEmail);
				valid = false;
			}
		});

		if (!valid) {
			var $first = $panel.find('.az-q-invalid').first();
			if ($first.length) {
				$('html, body').animate({ scrollTop: $first.closest('.az-q-field').offset().top - 80 }, 200);
			}
		}
		return valid;
	}

	function markInvalid($el, message) {
		$el.addClass('az-q-invalid');
		var $wrap = $el.closest('.az-q-field, .az-q-tooltip-wrap');
		if ($wrap.find('.az-q-field__error').length === 0) {
			$wrap.append('<span class="az-q-field__error" role="alert">' + escHtml(message) + '</span>');
		}
	}

	function isValidEmail(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}

	/* ── Repeater: Property ──────────────────────────── */
	function addPropertyRow() {
		var $rows = $('#property-rows');
		var idx   = $rows.find('.az-q-repeater__row').length;
		var html  = buildPropertyRow(idx, { description: '', value: '', awarded_to: 'petitioner' });
		$rows.append(html);
	}

	function buildPropertyRow(idx, values) {
		return '<div class="az-q-repeater__row" data-index="' + idx + '">' +
			'<input type="text" name="property_items[' + idx + '][description]" placeholder="' + escAttr(cfg.i18n.required ? 'e.g. Family home' : '') + '" value="' + escAttr(values.description || '') + '" />' +
			'<input type="text" name="property_items[' + idx + '][value]" placeholder="e.g. $250,000" value="' + escAttr(values.value || '') + '" />' +
			'<select name="property_items[' + idx + '][awarded_to]">' +
				'<option value="petitioner"' + (values.awarded_to === 'petitioner' ? ' selected' : '') + '>Petitioner</option>' +
				'<option value="respondent"' + (values.awarded_to === 'respondent' ? ' selected' : '') + '>Respondent</option>' +
			'</select>' +
			'<button type="button" class="az-q-repeater__remove" aria-label="Remove row">&#x2715;</button>' +
		'</div>';
	}

	/* ── Repeater: Debt ──────────────────────────────── */
	function addDebtRow() {
		var $rows = $('#debt-rows');
		var idx   = $rows.find('.az-q-repeater__row').length;
		var html  = buildDebtRow(idx, { creditor: '', balance: '', responsible_party: 'petitioner' });
		$rows.append(html);
	}

	function buildDebtRow(idx, values) {
		return '<div class="az-q-repeater__row" data-index="' + idx + '">' +
			'<input type="text" name="debt_items[' + idx + '][creditor]" placeholder="e.g. Bank of America" value="' + escAttr(values.creditor || '') + '" />' +
			'<input type="text" name="debt_items[' + idx + '][balance]" placeholder="e.g. $15,000" value="' + escAttr(values.balance || '') + '" />' +
			'<select name="debt_items[' + idx + '][responsible_party]">' +
				'<option value="petitioner"' + (values.responsible_party === 'petitioner' ? ' selected' : '') + '>Petitioner</option>' +
				'<option value="respondent"' + (values.responsible_party === 'respondent' ? ' selected' : '') + '>Respondent</option>' +
			'</select>' +
			'<button type="button" class="az-q-repeater__remove" aria-label="Remove row">&#x2715;</button>' +
		'</div>';
	}

	function removeRepeaterRow() {
		var $rows = $(this).closest('.az-q-repeater__rows');
		if ($rows.find('.az-q-repeater__row').length <= 1) { return; }
		$(this).closest('.az-q-repeater__row').remove();
		renumberRows($rows);
	}

	function renumberRows($rows) {
		var prefix = $rows.attr('id') === 'property-rows' ? 'property_items' : 'debt_items';
		$rows.find('.az-q-repeater__row').each(function (i) {
			$(this).attr('data-index', i);
			$(this).find('[name]').each(function () {
				var name = $(this).attr('name').replace(/\[\d+\]/, '[' + i + ']');
				$(this).attr('name', name.replace(/^[^\[]+/, prefix));
			});
		});
	}

	/* ── Conditional: former name ────────────────────── */
	function toggleFormerName() {
		var val = $('input[name="restore_former_name"]:checked').val();
		$('#former-name-field').toggleClass('az-q-visible', val === 'yes');
	}

	/* ── Prefill from JSON ───────────────────────────── */
	function loadPrefillData() {
		var $script = $('#az-q-prefill-data');
		if (!$script.length) { return; }
		var data;
		try { data = JSON.parse($script.text()); } catch (e) { return; }
		if (!data || typeof data !== 'object') { return; }

		$.each(data, function (key, val) {
			if (val === null || val === undefined) { return; }
			// Regular inputs / selects
			var $el = $wrapper.find('[name="' + key + '"]');
			if ($el.is('input[type="radio"]')) {
				$wrapper.find('input[name="' + key + '"][value="' + val + '"]').prop('checked', true);
			} else if ($el.length) {
				$el.val(val);
			}
		});
		toggleFormerName();
	}

	/* ── Status bar ──────────────────────────────────── */
	function setSaveStatus(state, msg) {
		var $bar = $('#az-q-save-status');
		$bar.removeClass('az-q-save-status--saving az-q-save-status--saved az-q-save-status--error');
		if (state === 'saving') {
			$bar.addClass('az-q-save-status--saving').text(cfg.i18n.saving);
		} else if (state === 'saved') {
			$bar.addClass('az-q-save-status--saved').text('✓ ' + cfg.i18n.saved);
			setTimeout(function () { $bar.text(''); }, 3000);
		} else if (state === 'error') {
			$bar.addClass('az-q-save-status--error').text(msg || cfg.i18n.saveError);
		}
	}

	/* ── Utilities ───────────────────────────────────── */
	function parseCompletedSteps(str) {
		if (!str) { return []; }
		return String(str).split(',').map(Number).filter(function (n) { return n > 0; });
	}

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function escAttr(str) {
		return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	/* ── Bootstrap ───────────────────────────────────── */
	$(document).ready(init);

}(jQuery));

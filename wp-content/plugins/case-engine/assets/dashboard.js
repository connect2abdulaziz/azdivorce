/**
 * Client dashboard — multi-step edit intake wizard.
 */
(function () {
	'use strict';

	function initEditIntake() {
		var root = document.getElementById('az-edit-intake');
		if (!root || root.getAttribute('data-wizard-ready') === '1') {
			return;
		}
		var form = document.getElementById('az-edit-intake-form');
		if (!form) {
			return;
		}
		root.setAttribute('data-wizard-ready', '1');

		var statusEl = document.getElementById('az-edit-intake-status');
		var panels = Array.prototype.slice.call(form.querySelectorAll('.az-edit-intake-panel'));
		var tabs = Array.prototype.slice.call(root.querySelectorAll('.az-edit-intake-steps__tab'));
		var kidsRows = document.getElementById('az-edit-children-rows');
		var kidsAgreement = document.getElementById('az-edit-children-agreement');
		var hasChildrenSelect = document.getElementById('az-edit-has-children');
		var btnPrev = document.getElementById('az-edit-prev');
		var btnNext = document.getElementById('az-edit-next');
		var btnSave = document.getElementById('az-edit-save');
		var addChildBtn = document.getElementById('az-add-child-row');
		var stepCurrentEl = document.getElementById('az-edit-step-current');
		var stepTotalEl = document.getElementById('az-edit-step-total');
		var progressFill = document.getElementById('az-edit-progress-fill');
		var idx = 0;

		function setHidden(el, hide) {
			if (!el) {
				return;
			}
			if (hide) {
				el.setAttribute('hidden', 'hidden');
				el.classList.add('is-hidden');
			} else {
				el.removeAttribute('hidden');
				el.classList.remove('is-hidden');
			}
		}

		function hasChildren() {
			return hasChildrenSelect && hasChildrenSelect.value === 'yes';
		}

		function visiblePanels() {
			return panels.filter(function (p) {
				if (p.getAttribute('data-requires-children') === '1') {
					return hasChildren();
				}
				return true;
			});
		}

		function showStep(i) {
			var visible = visiblePanels();
			if (!visible.length) {
				return;
			}
			idx = Math.max(0, Math.min(i, visible.length - 1));
			var active = visible[idx];
			panels.forEach(function (p) {
				var on = p === active;
				setHidden(p, !on);
				p.classList.toggle('is-active', on);
			});
			tabs.forEach(function (t) {
				var goto = parseInt(t.getAttribute('data-goto'), 10);
				var panel = panels[goto];
				var isVis = visible.indexOf(panel) !== -1;
				t.classList.toggle('is-active', isVis && panel === active);
				t.classList.toggle('is-done', isVis && visible.indexOf(panel) < idx);
			});
			if (stepCurrentEl) {
				stepCurrentEl.textContent = String(idx + 1);
			}
			if (stepTotalEl) {
				stepTotalEl.textContent = String(visible.length);
			}
			if (progressFill) {
				progressFill.style.width = Math.round(((idx + 1) / visible.length) * 100) + '%';
			}
			// Step 1: Back hidden. Middle: Next only. Last: Save only.
			setHidden(btnPrev, idx === 0);
			setHidden(btnNext, idx >= visible.length - 1);
			setHidden(btnSave, idx < visible.length - 1);
			setHidden(statusEl, true);
		}

		function syncChildrenVisibility() {
			var show = hasChildren();
			tabs.forEach(function (t) {
				if (t.getAttribute('data-requires-children') === '1') {
					setHidden(t, !show);
				}
			});
			if (kidsAgreement) {
				setHidden(kidsAgreement, !show);
				if (!show) {
					var radios = kidsAgreement.querySelectorAll('input[type="radio"]');
					for (var r = 0; r < radios.length; r++) {
						radios[r].checked = false;
					}
				}
			}
			var visible = visiblePanels();
			if (idx >= visible.length) {
				idx = Math.max(0, visible.length - 1);
			}
			showStep(idx);
		}

		function validatePanel(panel) {
			if (!panel) {
				return true;
			}
			var fields = panel.querySelectorAll('input, select, textarea');
			for (var i = 0; i < fields.length; i++) {
				var el = fields[i];
				if (el.disabled || el.hasAttribute('hidden')) {
					continue;
				}
				if (el.type === 'radio') {
					var name = el.name;
					if (name === 'children_agreement' && (!hasChildren() || (kidsAgreement && kidsAgreement.hasAttribute('hidden')))) {
						continue;
					}
					var group = panel.querySelectorAll('input[type="radio"][name="' + name + '"]');
					var anyRequired = false;
					var anyChecked = false;
					for (var g = 0; g < group.length; g++) {
						if (group[g].required || group[g].getAttribute('required') !== null) {
							anyRequired = true;
						}
						if (group[g].checked) {
							anyChecked = true;
						}
					}
					if (anyRequired && !anyChecked) {
						el.focus();
						setHidden(statusEl, false);
						if (statusEl) {
							statusEl.className = 'az-edit-intake-form__status is-error';
							statusEl.textContent = 'Please complete this step before continuing.';
						}
						return false;
					}
					continue;
				}
				if (el.type === 'checkbox' && el.required && !el.checked) {
					el.focus();
					setHidden(statusEl, false);
					if (statusEl) {
						statusEl.className = 'az-edit-intake-form__status is-error';
						statusEl.textContent = 'Please complete this step before continuing.';
					}
					return false;
				}
				if (typeof el.checkValidity === 'function' && el.willValidate && !el.checkValidity()) {
					if (typeof el.reportValidity === 'function') {
						el.reportValidity();
					}
					return false;
				}
			}
			return true;
		}

		if (hasChildrenSelect) {
			hasChildrenSelect.addEventListener('change', syncChildrenVisibility);
		}

		if (addChildBtn && kidsRows) {
			addChildBtn.addEventListener('click', function () {
				var i = kidsRows.querySelectorAll('.az-edit-child-row').length;
				var div = document.createElement('div');
				div.className = 'az-edit-intake-form__grid az-edit-child-row';
				div.innerHTML =
					'<label><span>Full name</span><input type="text" name="children[' + i + '][full_name]" /></label>' +
					'<label><span>Date of birth</span><input type="date" name="children[' + i + '][dob]" /></label>' +
					'<label><span>Relationship</span><input type="text" name="children[' + i + '][relationship]" placeholder="e.g. Son, Daughter" /></label>';
				kidsRows.appendChild(div);
			});
		}

		if (btnNext) {
			btnNext.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var visible = visiblePanels();
				if (!validatePanel(visible[idx])) {
					return;
				}
				showStep(idx + 1);
			});
		}

		if (btnPrev) {
			btnPrev.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				showStep(idx - 1);
			});
		}

		tabs.forEach(function (t) {
			t.addEventListener('click', function (e) {
				e.preventDefault();
				var goto = parseInt(t.getAttribute('data-goto'), 10);
				var panel = panels[goto];
				var visible = visiblePanels();
				var target = visible.indexOf(panel);
				if (target === -1) {
					return;
				}
				if (target > idx && !validatePanel(visible[idx])) {
					return;
				}
				showStep(target);
			});
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var visible = visiblePanels();
			// Only allow save on the last step.
			if (idx < visible.length - 1) {
				if (!validatePanel(visible[idx])) {
					return;
				}
				showStep(idx + 1);
				return;
			}
			if (!validatePanel(visible[idx])) {
				return;
			}
			setHidden(statusEl, true);
			var fd = new FormData(form);
			fd.append('action', 'az_client_save_intake');
			fd.append('nonce', root.getAttribute('data-nonce'));
			fd.append('case_id', root.getAttribute('data-case-id'));
			if (!hasChildren()) {
				fd.set('children_agreement', '');
			}
			if (btnSave) {
				btnSave.disabled = true;
			}
			fetch(root.getAttribute('data-ajax-url'), {
				method: 'POST',
				body: fd,
				credentials: 'same-origin',
			})
				.then(function (r) {
					return r.json();
				})
				.then(function (res) {
					if (btnSave) {
						btnSave.disabled = false;
					}
					if (res && res.success && res.data && res.data.redirect) {
						window.location.href = res.data.redirect;
						return;
					}
					setHidden(statusEl, false);
					if (statusEl) {
						statusEl.className = 'az-edit-intake-form__status is-error';
						statusEl.textContent =
							res && res.data && res.data.message
								? res.data.message
								: 'Could not save. Please try again.';
					}
				})
				.catch(function () {
					if (btnSave) {
						btnSave.disabled = false;
					}
					setHidden(statusEl, false);
					if (statusEl) {
						statusEl.className = 'az-edit-intake-form__status is-error';
						statusEl.textContent = 'Could not save. Please try again.';
					}
				});
		});

		syncChildrenVisibility();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initEditIntake);
	} else {
		initEditIntake();
	}
})();

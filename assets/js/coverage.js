/**
 * Coverage Calculator: links the area input to the WooCommerce quantity
 * (pieces) and fills variation fields into the sample form. No dependencies.
 *
 * Dispatches a bubbling `coverage:change` CustomEvent from the calculator box
 * with detail { area, pieces, perUnit, price, productId, variationId }; the
 * latest detail is also kept as `coverageState` on the box element.
 */
(function () {
	'use strict';

	var cfg = window.coverageCalc || {};
	var PRECISION = 6;

	function round(n) {
		return Number(Number(n).toFixed(PRECISION));
	}

	function parse(raw) {
		var s = String(raw || '').replace(/[\s\u00a0\u202f]/g, '');
		if (s.indexOf(',') !== -1 && s.indexOf('.') !== -1) {
			s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
		} else {
			s = s.replace(',', '.');
		}
		var n = parseFloat(s);
		return isFinite(n) && n > 0 ? n : 0;
	}

	function piecesFor(area, perUnit) {
		return area > 0 && perUnit > 0 ? Math.ceil(round(area / perUnit)) : 0;
	}

	function areaFor(pieces, perUnit) {
		return pieces > 0 && perUnit > 0 ? round(pieces * perUnit) : 0;
	}

	function formatArea(n) {
		return n.toFixed(2).replace('.', cfg.decimalSeparator || '.');
	}

	function formatPerUnit(n) {
		return String(Number(n.toFixed(4))).replace('.', cfg.decimalSeparator || '.');
	}

	function initCalculator(box) {
		var form = box.closest('form');
		var qty = form ? form.querySelector('input[name="quantity"]') : null;
		var input = box.querySelector('.coverage-calc__area');
		var perUnitLabel = box.querySelector('.coverage-calc__per-unit');
		if (!qty || !input) {
			return;
		}

		var basePerUnit = parseFloat(box.dataset.perUnit) || 0;
		var basePrice = box.dataset.price === '' ? null : parseFloat(box.dataset.price);
		var variations = {};
		try {
			variations = JSON.parse(box.dataset.variations || '{}');
		} catch (e) {}

		var state = { perUnit: basePerUnit, price: basePrice, variationId: 0 };
		var syncing = false;

		function pieces() {
			return parseInt(qty.value, 10) || 0;
		}

		function emit() {
			// Kept on the box too, so scripts that start later can read the current state.
			box.coverageState = {
				area: areaFor(pieces(), state.perUnit),
				pieces: pieces(),
				perUnit: state.perUnit,
				price: state.price,
				productId: parseInt(box.dataset.productId, 10) || 0,
				variationId: state.variationId
			};
			box.dispatchEvent(new CustomEvent('coverage:change', { bubbles: true, detail: box.coverageState }));
		}

		function fromQuantity() {
			if (syncing) {
				return;
			}
			var area = areaFor(pieces(), state.perUnit);
			input.value = area > 0 ? formatArea(area) : '';
			emit();
		}

		function fromArea(normalize) {
			var p = piecesFor(parse(input.value), state.perUnit);
			if (p > 0) {
				syncing = true;
				qty.value = String(p);
				qty.dispatchEvent(new Event('change', { bubbles: true }));
				syncing = false;
			}
			if (normalize) {
				fromQuantity();
			} else {
				emit();
			}
		}

		function refreshVariation() {
			var field = form.querySelector('input[name="variation_id"]');
			var id = field ? parseInt(field.value, 10) || 0 : 0;
			var v = id ? variations[id] : null;
			state.variationId = id;
			state.perUnit = v && v.u > 0 ? v.u : basePerUnit;
			state.price = v && v.p !== null ? v.p : basePrice;
			box.hidden = !(state.perUnit > 0);
			if (perUnitLabel) {
				perUnitLabel.textContent = formatPerUnit(state.perUnit);
			}
			fromQuantity();
		}

		input.addEventListener('input', function () { fromArea(false); });
		input.addEventListener('change', function () { fromArea(true); });
		qty.addEventListener('input', fromQuantity);
		qty.addEventListener('change', fromQuantity);

		var variationField = form.querySelector('input[name="variation_id"]');
		if (variationField) {
			// WooCommerce sets the variation with jQuery .val().trigger('change'),
			// which runs inline on* handlers but not addEventListener listeners.
			variationField.onchange = refreshVariation;
			form.addEventListener('change', function (e) {
				if (e.target !== variationField) {
					window.setTimeout(refreshVariation, 0);
				}
			});
			refreshVariation();
		} else {
			fromQuantity();
		}
	}

	function initSampleForm(sampleForm) {
		sampleForm.addEventListener('submit', function () {
			var cart = document.querySelector('form.variations_form');
			if (!cart) {
				return;
			}
			sampleForm.querySelectorAll('.coverage-sample__copied').forEach(function (el) { el.remove(); });
			cart.querySelectorAll('[name="variation_id"], [name^="attribute_"]').forEach(function (field) {
				var copy = document.createElement('input');
				copy.type = 'hidden';
				copy.className = 'coverage-sample__copied';
				copy.name = field.name;
				copy.value = field.value;
				sampleForm.appendChild(copy);
			});
		});
	}

	function init() {
		document.querySelectorAll('.coverage-calc').forEach(initCalculator);
		document.querySelectorAll('form.coverage-sample').forEach(initSampleForm);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

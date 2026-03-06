(function ($) {
	'use strict';

	function getAttributes($configurator) {
		var attributes = {};

		$configurator.find('.tpcw-attribute-render').each(function () {
			var $attribute = $(this);
			var attributeKey = String($attribute.data('attribute-key') || '');
			if (!attributeKey) {
				return;
			}

			var value = '';
			var $select = $attribute.find('.tpcw-select');
			if ($select.length) {
				value = String($select.val() || '');
			} else {
				var $selectedCard = $attribute.find('.tpcw-option-card.is-selected');
				if ($selectedCard.length) {
					value = String($selectedCard.data('value-key') || '');
				}
			}

			if (value) {
				attributes[attributeKey] = value;
			}
		});

		return attributes;
	}

	function getExtras($configurator) {
		var extras = [];
		$configurator.find('.tpcw-service-toggle:checked').each(function () {
			extras.push(String($(this).val() || ''));
		});
		return extras;
	}

	function stableStringify(obj) {
		if (Array.isArray(obj)) {
			return '[' + obj.map(stableStringify).join(',') + ']';
		}
		if (obj && typeof obj === 'object') {
			var keys = Object.keys(obj).sort();
			return '{' + keys.map(function (k) { return JSON.stringify(k) + ':' + stableStringify(obj[k]); }).join(',') + '}';
		}
		return JSON.stringify(obj);
	}


	function preloadPreviewImages($configurator) {
		$configurator.find('.tpcw-preview-thumb').each(function () {
			var src = String($(this).data('image-url') || '');
			if (src) {
				var img = new Image();
				img.src = src;
			}
		});
	}

	function updatePreviewImage($configurator, selectedAttributes) {
		var $main = $('#tpcw-preview-main-image');
		if (!$main.length) {
			return;
		}

		var $thumbs = $configurator.find('.tpcw-preview-thumb');
		var best = null;
		$thumbs.each(function () {
			var $thumb = $(this);
			var aKey = String($thumb.data('attribute-key') || '');
			var oKey = String($thumb.data('option-key') || '');
			var sort = parseInt($thumb.data('sort-order') || 0, 10);
			if (aKey && oKey && selectedAttributes[aKey] && String(selectedAttributes[aKey]) === oKey) {
				if (!best || sort < best.sort) {
					best = { el: $thumb, src: String($thumb.data('image-url') || ''), sort: sort };
				}
			}
		});

		var targetSrc = best && best.src ? best.src : String($configurator.find('.tpcw-preview').data('default-image') || '');
		if (!targetSrc || $main.attr('src') === targetSrc) {
			$thumbs.removeClass('is-active');
			if (best) {
				best.el.addClass('is-active');
			}
			return;
		}

		$main.addClass('is-fading');
		window.setTimeout(function () {
			$main.attr('src', targetSrc);
			$main.removeClass('is-fading');
		}, 150);

		$thumbs.removeClass('is-active');
		if (best) {
			best.el.addClass('is-active');
		}
	}

	function showLoading($configurator, text) {
		$configurator.addClass('tpcw-loading');
		$configurator.find('.tpcw-summary-content').html('<p class="tpcw-loading-text">' + text + '</p>');
	}

	function clearLoading($configurator) {
		$configurator.removeClass('tpcw-loading');
	}

	function showInlineMessage(message, isError) {
		var $target = $('#tpcw-cart-validation');
		if (!$target.length) {
			return;
		}
		$target.text(message || '');
		$target.toggleClass('is-error', !!isError);
	}

	function renderSummary($configurator, response) {
		if (!response || !response.success) {
			return;
		}

		var pricingMode = String($configurator.data('pricing-mode') || 'standard');
		var lines = Array.isArray(response.summary_lines) ? response.summary_lines : [];
		var html = '<ul>';

		lines.forEach(function (line) {
			html += '<li><strong>' + line.label + ':</strong> ' + line.value + '</li>';
		});

		html += '<li><strong>' + (tpcwFrontend.pricingModeLabel || 'Pricing mode') + ':</strong> ' + pricingMode.charAt(0).toUpperCase() + pricingMode.slice(1) + '</li>';
		if (response.base_price_html) {
			html += '<li class="tpcw-muted"><strong>Base price:</strong> ' + response.base_price_html + '</li>';
		}
		if (response.final_display_price_html) {
			html += '<li><strong>Total:</strong> ' + response.final_display_price_html + '</li>';
		}
		if (response.unit_price_html) {
			html += '<li><strong>Unit price:</strong> ' + response.unit_price_html + '</li>';
		}
		if (response.estimated_delivery) {
			html += '<li><strong>Delivery estimate:</strong> ' + response.estimated_delivery + '</li>';
		}
		if (response.pricing_status === 'custom_pending' && response.message) {
			html += '<li><strong>Status:</strong> ' + response.message + '</li>';
		}
		html += '</ul>';

		$configurator.find('.tpcw-summary-content').html(html);
	}

	function setCartPayload($configurator, state, explicitPayload) {
		var payload = explicitPayload || {
			product_id: parseInt($configurator.data('product-id'), 10) || 0,
			selected_attributes: getAttributes($configurator),
			selected_service: state.selectedService || '',
			selected_quantity: state.selectedQuantity || 0,
			custom_quantity: state.customQuantity || 0,
			selected_extra_services: getExtras($configurator),
			resolved_pricing_payload: state.lastResponse || null,
			pricing_mode: String($configurator.data('pricing-mode') || 'standard')
		};

		$('#tpcw-config-payload').val(JSON.stringify(payload));
		updatePreviewImage($configurator, payload.selected_attributes || {});
	}

	function requestPricing($configurator, payload, state, pendingCell) {
		var cacheKey = stableStringify(payload);
		if (state.cache[cacheKey]) {
			state.lastResponse = state.cache[cacheKey];
			renderSummary($configurator, state.lastResponse);
			if (pendingCell && pendingCell.length && state.lastResponse.pricing_status === 'exact_match') {
				$configurator.find('.tpcw-matrix-cell').removeClass('is-selected is-pending');
				pendingCell.addClass('is-selected').removeClass('is-pending');
			}
			setCartPayload($configurator, state, payload);
			return;
		}

		if (state.inFlight) {
			return;
		}

		state.inFlight = true;
		showLoading($configurator, tpcwFrontend.loadingLabel || 'Updating price…');
		showInlineMessage('', false);

		fetch(tpcwFrontend.priceEndpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
			.then(function (response) {
				return response.json().then(function (data) {
					if (!response.ok) {
						throw data;
					}
					return data;
				});
			})
			.then(function (data) {
				state.cache[cacheKey] = data;
				state.lastResponse = data;
				renderSummary($configurator, data);

				if (pendingCell && pendingCell.length && data.pricing_status === 'exact_match') {
					$configurator.find('.tpcw-matrix-cell').removeClass('is-selected is-pending');
					pendingCell.addClass('is-selected').removeClass('is-pending');
				} else if (pendingCell) {
					pendingCell.removeClass('is-pending');
				}

				setCartPayload($configurator, state, payload);
			})
			.catch(function (errorResponse) {
				if (pendingCell && pendingCell.length) {
					pendingCell.removeClass('is-pending');
				}
				if (state.lastResponse) {
					renderSummary($configurator, state.lastResponse);
				}
				showInlineMessage((errorResponse && errorResponse.message) ? errorResponse.message : (tpcwFrontend.errorLabel || 'Unable to update pricing right now. Please try again.'), true);
			})
			.finally(function () {
				state.inFlight = false;
				clearLoading($configurator);
			});
	}

	$(function () {
		var $configurator = $('#tpcw-configurator');
		if (!$configurator.length || !window.tpcwFrontend || !tpcwFrontend.priceEndpoint) {
			return;
		}

		var state = {
			cache: {},
			inFlight: false,
			lastResponse: null,
			selectedService: '',
			selectedQuantity: 0,
			customQuantity: 0
		};

		function buildPayload() {
			return {
				product_id: parseInt($configurator.data('product-id'), 10) || 0,
				selected_attributes: getAttributes($configurator),
				selected_service: state.selectedService,
				selected_quantity: state.selectedQuantity,
				custom_quantity: state.customQuantity,
				selected_extra_services: getExtras($configurator)
			};
		}

		$configurator.on('click', '.tpcw-option-card', function () {
			var $clicked = $(this);
			var key = $clicked.data('attribute-key');
			$configurator.find('.tpcw-option-card[data-attribute-key="' + key + '"]').removeClass('is-selected');
			$clicked.addClass('is-selected');
			setCartPayload($configurator, state);
		});

		$configurator.on('change', '.tpcw-select, .tpcw-service-toggle', function () {
			setCartPayload($configurator, state);
		});

		$configurator.on('click', '.tpcw-matrix-cell:not([disabled])', function () {
			var $clicked = $(this);
			state.customQuantity = 0;
			state.selectedQuantity = parseInt($clicked.data('quantity'), 10) || 0;
			state.selectedService = String($clicked.data('service-key') || '');
			$clicked.addClass('is-pending');
			requestPricing($configurator, buildPayload(), state, $clicked);
		});

		$('#tpcw-add-custom-qty').on('click', function () {
			var value = parseInt($('#tpcw-custom-qty').val(), 10);
			if (isNaN(value) || value <= 0) {
				showInlineMessage('Please enter a valid custom quantity.', true);
				return;
			}

			$configurator.find('.tpcw-matrix-cell').removeClass('is-selected is-pending');
			state.customQuantity = value;
			state.selectedQuantity = 0;
			state.selectedService = '';
			requestPricing($configurator, buildPayload(), state, null);
		});

		preloadPreviewImages($configurator);
		setCartPayload($configurator, state);
		$configurator.find('.tpcw-summary-content').html('<p>Select a matrix option to load pricing.</p>');
	});
})(jQuery);
